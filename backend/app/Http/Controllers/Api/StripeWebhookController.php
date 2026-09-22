<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\StripeWebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StripeWebhookEvent;
use App\Services\CardoraProSubscriptionService;
use App\Services\OrderCheckoutService;
use App\Services\SellerLedgerService;
use App\Services\StripeConnectService;
use App\Services\FeaturedListingPaymentService;
use App\Services\StripeMarketplaceService;
use App\Services\TradeEscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        StripeMarketplaceService $marketplaceService,
        StripeConnectService $connectService,
        SellerLedgerService $ledgerService,
        OrderCheckoutService $checkoutService,
        FeaturedListingPaymentService $featuredService,
        TradeEscrowService $tradeEscrow,
        CardoraProSubscriptionService $proSubscriptions
    ) {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Stripe webhook verification failed.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $eventRecord = StripeWebhookEvent::firstOrNew([
            'stripe_event_id' => $event->id,
        ]);

        if ($eventRecord->exists && $eventRecord->status === StripeWebhookEventStatus::Processed->value) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $eventRecord->fill([
            'type' => $event->type,
            'account' => $event->account,
            'api_version' => $event->api_version,
            'livemode' => (bool) $event->livemode,
            'status' => StripeWebhookEventStatus::Pending->value,
            'payload' => json_decode($payload, true),
            'error_message' => null,
        ]);
        $eventRecord->save();

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $sessionType = $event->data->object->metadata->type ?? null;
                    if ($sessionType === 'trade_deposit') {
                        $tradeEscrow->handleCheckoutSessionCompleted($event->data->object);
                    } elseif ($sessionType === 'featured_listing') {
                        $featuredService->markPaidFromWebhook($event->data->object);
                    } elseif ($sessionType === 'cardora_pro_subscription') {
                        $this->handleProCheckoutCompleted($event->data->object, $proSubscriptions);
                    } else {
                        $marketplaceService->handleCheckoutSessionCompleted($event->data->object);
                    }
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    if (($event->data->object->metadata->type ?? null) === 'cardora_pro_subscription') {
                        $proSubscriptions->syncFromStripeSubscription($event->data->object);
                    }
                    break;

                case 'invoice.paid':
                    $this->handleInvoicePaid($event->data->object, $proSubscriptions);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object, $proSubscriptions);
                    break;

                case 'checkout.session.expired':
                    $this->handleCheckoutSessionExpired($event->data->object, $marketplaceService);
                    break;

                case 'payment_intent.succeeded':
                    if (($event->data->object->metadata->type ?? null) === 'trade_deposit') {
                        $tradeEscrow->handlePaymentIntentSucceeded($event->data->object);
                    } else {
                        $marketplaceService->handlePaymentIntentSucceeded($event->data->object);
                    }
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object, $marketplaceService);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event->data->object, $checkoutService, $ledgerService);
                    break;

                case 'account.updated':
                    $this->handleAccountUpdated($event, $connectService);
                    break;

                case 'payout.paid':
                    $this->handlePayoutPaid($event, $connectService, $ledgerService);
                    break;

                case 'payout.failed':
                    $this->handlePayoutFailed($event, $connectService, $ledgerService);
                    break;
            }

            $eventRecord->forceFill([
                'status' => StripeWebhookEventStatus::Processed->value,
                'processed_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            Log::error('Stripe webhook processing failed.', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $exception->getMessage(),
            ]);

            $eventRecord->forceFill([
                'status' => StripeWebhookEventStatus::Failed->value,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        return response()->json(['received' => true]);
    }

    protected function handlePaymentIntentFailed(object $paymentIntent, StripeMarketplaceService $marketplaceService): void
    {
        // A batch checkout shares one payment_intent across every order it produced, so a
        // failure has to be applied to all of them, not just one.
        $orders = $marketplaceService->findOrdersByStripeMetadata(
            (array) ($paymentIntent->metadata ?? []),
            $paymentIntent->id ?? null
        );

        foreach ($orders as $order) {
            $marketplaceService->markPaymentFailed($order, [
                'payment_intent_id' => $paymentIntent->id ?? null,
                'last_payment_error' => $paymentIntent->last_payment_error?->message ?? null,
            ]);
        }
    }

    protected function handleCheckoutSessionExpired(object $session, StripeMarketplaceService $marketplaceService): void
    {
        $orders = $marketplaceService->findOrdersByStripeMetadata(
            (array) ($session->metadata ?? []),
            $session->payment_intent ?? null,
            $session->id ?? null
        );

        foreach ($orders as $order) {
            $marketplaceService->markPaymentFailed($order, [
                'checkout_session_id' => $session->id ?? null,
                'reason' => 'checkout_session_expired',
            ]);
        }
    }

    protected function handleChargeRefunded(object $charge, OrderCheckoutService $checkoutService, SellerLedgerService $ledgerService): void
    {
        // A batch checkout's orders share one Stripe charge, so a refund against that charge
        // (even a partial one) can't be reliably attributed to a single order from webhook data
        // alone — flag every order on the charge for review rather than silently handling just
        // the one `first()` used to pick before batch checkouts existed.
        $orders = Order::query()
            ->where('stripe_charge_id', $charge->id ?? '')
            ->orWhere('stripe_payment_intent_id', $charge->payment_intent ?? '')
            ->get();

        foreach ($orders as $order) {
            $this->applyChargeRefundToOrder($order, $charge, $checkoutService, $ledgerService);
        }
    }

    protected function applyChargeRefundToOrder(Order $order, object $charge, OrderCheckoutService $checkoutService, SellerLedgerService $ledgerService): void
    {
        if ($order->status === OrderStatus::Refunded->value) {
            return;
        }

        if ($order->isReleased()) {
            $order->forceFill([
                'status' => OrderStatus::Disputed->value,
                'escrow_status' => OrderStatus::Disputed->value,
                'metadata' => array_merge($order->metadata ?? [], [
                    'refund_after_release' => [
                        'stripe_charge_id' => $charge->id ?? null,
                        'refunded_amount' => $charge->amount_refunded ?? null,
                    ],
                ]),
            ])->save();

            $ledgerService->recordPostReleaseRefundAlert($order, [
                'stripe_charge_id' => $charge->id ?? null,
                'amount_refunded' => $charge->amount_refunded ?? null,
                'source' => 'stripe_webhook',
            ]);

            return;
        }

        $refundedOrder = $checkoutService->markOrderRefundedBeforeRelease($order, [
            'stripe_charge_id' => $charge->id ?? null,
            'amount_refunded' => $charge->amount_refunded ?? null,
            'source' => 'stripe_webhook',
        ]);

        $ledgerService->reversePendingFundsForRefund($refundedOrder, [
            'stripe_charge_id' => $charge->id ?? null,
            'amount_refunded' => $charge->amount_refunded ?? null,
            'source' => 'stripe_webhook',
        ]);
    }

    protected function handleProCheckoutCompleted(object $session, CardoraProSubscriptionService $proSubscriptions): void
    {
        $subscriptionId = is_string($session->subscription ?? null)
            ? $session->subscription
            : ($session->subscription->id ?? null);

        if (! $subscriptionId) {
            return;
        }

        $stripeSubscription = $proSubscriptions->retrieveSubscription($subscriptionId);
        $subscription = $proSubscriptions->syncFromStripeSubscription($stripeSubscription);

        if ($subscription && $subscription->trial_ends_at !== null) {
            $proSubscriptions->markTrialConsumed($subscription->user);
        }
    }

    protected function handleInvoicePaid(object $invoice, CardoraProSubscriptionService $proSubscriptions): void
    {
        $subscriptionId = $this->resolveInvoiceSubscriptionId($invoice);
        if (! $subscriptionId) {
            return;
        }

        $subscription = \App\Models\Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if (! $subscription) {
            return;
        }

        $proSubscriptions->syncFromStripeSubscription($proSubscriptions->retrieveSubscription($subscriptionId));
        $proSubscriptions->grantFeaturedCredit($subscription->user);
    }

    protected function handleInvoicePaymentFailed(object $invoice, CardoraProSubscriptionService $proSubscriptions): void
    {
        $subscriptionId = $this->resolveInvoiceSubscriptionId($invoice);
        if (! $subscriptionId) {
            return;
        }

        $exists = \App\Models\Subscription::query()->where('stripe_subscription_id', $subscriptionId)->exists();
        if (! $exists) {
            return;
        }

        $proSubscriptions->syncFromStripeSubscription($proSubscriptions->retrieveSubscription($subscriptionId));
    }

    /**
     * Stripe moved the invoice -> subscription link around across API
     * versions — try the legacy top-level field first, then the current
     * parent.subscription_details.subscription location.
     */
    protected function resolveInvoiceSubscriptionId(object $invoice): ?string
    {
        if (is_string($invoice->subscription ?? null)) {
            return $invoice->subscription;
        }

        if (! empty($invoice->subscription->id ?? null)) {
            return $invoice->subscription->id;
        }

        $viaParent = $invoice->parent->subscription_details->subscription ?? null;
        if (is_string($viaParent)) {
            return $viaParent;
        }

        return $viaParent->id ?? null;
    }

    protected function handleAccountUpdated(object $event, StripeConnectService $connectService): void
    {
        $stripeAccountId = $event->account ?: ($event->data->object->id ?? null);

        if (! $stripeAccountId) {
            return;
        }

        $localAccount = $connectService->findLocalAccountByStripeId($stripeAccountId);

        if (! $localAccount) {
            return;
        }

        $connectService->syncLocalAccount($localAccount, $event->data->object);
    }

    protected function handlePayoutPaid(object $event, StripeConnectService $connectService, SellerLedgerService $ledgerService): void
    {
        $stripeAccountId = $event->account ?? null;
        $localAccount = $stripeAccountId ? $connectService->findLocalAccountByStripeId($stripeAccountId) : null;

        if (! $localAccount) {
            return;
        }

        $payout = $event->data->object;

        $ledgerService->markPayoutPaid($localAccount, [
            'stripe_payout_id' => $payout->id,
            'amount' => $payout->amount,
            'currency' => $payout->currency,
            'destination' => $payout->destination ?? null,
            'requested_at' => $payout->created ?? null,
            'processed_at' => $payout->arrival_date ?? null,
            'arrival_date' => $payout->arrival_date ?? null,
            'metadata' => [
                'method' => $payout->method ?? null,
                'status' => $payout->status ?? null,
            ],
        ]);
    }

    protected function handlePayoutFailed(object $event, StripeConnectService $connectService, SellerLedgerService $ledgerService): void
    {
        $stripeAccountId = $event->account ?? null;
        $localAccount = $stripeAccountId ? $connectService->findLocalAccountByStripeId($stripeAccountId) : null;

        if (! $localAccount) {
            return;
        }

        $payout = $event->data->object;

        $ledgerService->markPayoutFailed($localAccount, [
            'stripe_payout_id' => $payout->id,
            'amount' => $payout->amount,
            'currency' => $payout->currency,
            'destination' => $payout->destination ?? null,
            'requested_at' => $payout->created ?? null,
            'processed_at' => now()->timestamp,
            'arrival_date' => $payout->arrival_date ?? null,
            'failure_code' => $payout->failure_code ?? null,
            'failure_message' => $payout->failure_message ?? null,
            'metadata' => [
                'method' => $payout->method ?? null,
                'status' => $payout->status ?? null,
            ],
        ]);
    }
}
