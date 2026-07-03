<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\CreateBoxNowShipmentForOrderJob;
use App\Jobs\CreateDhlShipmentForOrderJob;
use App\Mail\MarketplaceEventMail;
use App\Models\Order;
use App\Models\SellerPayoutAccount;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeMarketplaceService
{
    protected ?StripeClient $client = null;

    public function __construct(
        protected OrderCheckoutService $checkoutService,
        protected StripeConnectService $connectService,
        protected SellerLedgerService $ledgerService,
        protected StripePlatformBalanceService $platformBalanceService,
        protected PlatformVolumeCampaignService $platformVolumeCampaigns,
        protected MarketplaceNotificationService $notifications
    ) {
    }

    public function createCheckoutSessionForOrder(Order $order): array
    {
        $order->loadMissing(['buyer', 'seller', 'items.listing.product', 'items.drawCampaign']);

        if ($order->stripe_checkout_session_id) {
            try {
                $existing = $this->stripe()->checkout->sessions->retrieve($order->stripe_checkout_session_id, []);

                if (($existing->status ?? null) === 'open' && ! empty($existing->url)) {
                    return [
                        'checkout_session_id' => $existing->id,
                        'checkout_url' => $existing->url,
                        'expires_at' => $existing->expires_at,
                        'order_id' => $order->getKey(),
                        'order_number' => $order->order_number,
                    ];
                }
            } catch (ApiErrorException $exception) {
                Log::warning('Unable to reuse Stripe Checkout session for order.', [
                    'order_id' => $order->getKey(),
                    'stripe_checkout_session_id' => $order->stripe_checkout_session_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $session = $this->stripe()->checkout->sessions->create(
            [
                'mode' => 'payment',
                'success_url' => $this->checkoutSuccessUrl($order),
                'cancel_url' => $this->checkoutCancelUrl($order),
                'customer_email' => $order->buyer?->email,
                'client_reference_id' => (string) $order->getKey(),
                'line_items' => $this->buildCheckoutLineItems($order),
                'metadata' => $this->stripeMetadataForOrder($order),
                'payment_intent_data' => [
                    'metadata' => $this->stripeMetadataForOrder($order),
                    'transfer_group' => $this->transferGroup($order),
                ],
            ],
            [
                'idempotency_key' => sprintf('checkout_session_order_%s', $order->getKey()),
            ]
        );

        $order->forceFill([
            'stripe_checkout_session_id' => $session->id,
        ])->save();

        return [
            'checkout_session_id' => $session->id,
            'checkout_url' => $session->url,
            'expires_at' => $session->expires_at,
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
        ];
    }

    public function createPaymentIntentForOrder(Order $order): object
    {
        $order->loadMissing(['buyer']);

        return $this->stripe()->paymentIntents->create(
            [
                'amount' => $this->toStripeAmount((float) $order->total_amount),
                'currency' => strtolower($order->currency),
                'automatic_payment_methods' => ['enabled' => true],
                'receipt_email' => $order->buyer?->email,
                'metadata' => $this->stripeMetadataForOrder($order),
                'transfer_group' => $this->transferGroup($order),
            ],
            [
                'idempotency_key' => sprintf('payment_intent_order_%s', $order->getKey()),
            ]
        );
    }

    public function confirmSuccessfulPayment(Order $order, array $paymentPayload = []): Order
    {
        $order = $this->syncOrderPaymentReferences($order, $paymentPayload);

        if (in_array($order->status, [OrderStatus::PaidPendingRelease->value, OrderStatus::Released->value], true)) {
            return $order->fresh(['items', 'buyer', 'seller', 'escrowTransaction']);
        }

        if ($order->status !== OrderStatus::PendingPayment->value) {
            throw ValidationException::withMessages([
                'order' => ['Only pending-payment orders can be marked as paid.'],
            ]);
        }

        $paidOrder = $this->checkoutService->finalizeSuccessfulPayment($order, $paymentPayload);
        $this->ledgerService->recordPaymentCaptured($paidOrder);
        $this->platformBalanceService->syncHeldFundsReserve();

        match ((string) $paidOrder->shipping_carrier) {
            'dhl_express' => CreateDhlShipmentForOrderJob::dispatch($paidOrder->getKey()),
            'boxnow' => CreateBoxNowShipmentForOrderJob::dispatch($paidOrder->getKey()),
            default => null,
        };

        return $paidOrder->fresh(['items', 'buyer', 'seller', 'escrowTransaction']);
    }

    public function confirmSession(string $sessionId, ?User $buyer = null, ?int $orderId = null): ?Order
    {
        try {
            $session = $this->stripe()->checkout->sessions->retrieve($sessionId, []);
        } catch (ApiErrorException $exception) {
            Log::warning('Unable to confirm order payment from Stripe Checkout session.', [
                'session_id' => $sessionId,
                'order_id' => $orderId,
                'buyer_id' => $buyer?->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (($session->payment_status ?? null) !== 'paid') {
            return null;
        }

        $order = $this->findOrderByStripeMetadata(
            (array) ($session->metadata ?? []),
            $session->payment_intent ?? null,
            $session->id ?? null
        );

        if (! $order && $orderId) {
            $order = Order::query()->find($orderId);
        }

        if (! $order) {
            Log::warning('Paid Stripe Checkout session could not be matched to an order.', [
                'session_id' => $sessionId,
                'order_id' => $orderId,
                'buyer_id' => $buyer?->getKey(),
                'metadata' => $session->metadata ?? [],
            ]);

            return null;
        }

        if ($buyer && (int) $order->buyer_id !== (int) $buyer->getKey()) {
            throw ValidationException::withMessages([
                'session_id' => [__('api.errors.forbidden')],
            ]);
        }

        return $this->handleCheckoutSessionCompleted($session);
    }

    public function releaseFundsToSeller(Order $order, array $context = []): Order
    {
        $order->loadMissing(['seller.sellerPayoutAccount', 'escrowTransaction']);
        $order->refresh();

        if ($order->isReleased()) {
            return $order->fresh(['seller.sellerPayoutAccount', 'escrowTransaction']);
        }

        if (! $order->isPaidPendingRelease()) {
            throw ValidationException::withMessages([
                'order' => ['Only paid orders pending release can be released to the seller.'],
            ]);
        }

        if (
            empty($context['manual_release'])
            && empty($context['force_release'])
            && ! $order->delivered_at
        ) {
            throw ValidationException::withMessages([
                'order' => ['Funds can be released only after DHL confirms delivery.'],
            ]);
        }

        $payoutAccount = $order->seller?->sellerPayoutAccount;

        if (! $payoutAccount?->stripe_account_id) {
            throw ValidationException::withMessages([
                'seller' => ['The seller has not connected a Stripe payout account yet.'],
            ]);
        }

        $payoutAccount = $this->connectService->syncLocalAccount($payoutAccount);

        if (! $payoutAccount->isFullyOnboarded()) {
            throw ValidationException::withMessages([
                'seller' => ['The seller Stripe account is not fully ready for transfers and payouts.'],
            ]);
        }

        if ($order->stripe_transfer_id) {
            return $order->fresh(['seller.sellerPayoutAccount', 'escrowTransaction']);
        }

        $transfer = $this->createTransferToSeller($order->fresh());

        $order->forceFill([
            'status' => OrderStatus::Released->value,
            'escrow_status' => OrderStatus::Released->value,
            'stripe_transfer_id' => $transfer->id,
            'released_at' => now(),
            'completed_at' => $order->completed_at ?: now(),
            'buyer_confirmed_at' => ! empty($context['buyer_confirmed'])
                ? ($order->buyer_confirmed_at ?: ($context['confirmed_at'] ?? now()))
                : $order->buyer_confirmed_at,
        ])->save();

        $order->escrowTransaction()?->update([
            'status' => OrderStatus::Released->value,
            'released_at' => now(),
            'provider_reference' => $transfer->id,
            'metadata' => array_merge($order->escrowTransaction?->metadata ?? [], [
                'stripe_transfer_id' => $transfer->id,
                'release_context' => $context,
            ]),
        ]);

        $this->ledgerService->recordFundsRelease($order->fresh(['seller.sellerPayoutAccount']), $transfer->id);
        $this->platformBalanceService->syncHeldFundsReserve();
        $this->platformVolumeCampaigns->syncAll();
        $releasedOrder = $order->fresh(['buyer', 'seller', 'seller.sellerPayoutAccount', 'escrowTransaction']);

        $this->notifications->createForUser(
            (int) $releasedOrder->seller_id,
            'order_released',
            app()->getLocale() === 'en'
                ? sprintf('Funds released for order %s', $releasedOrder->order_number)
                : sprintf('Αποδεσμεύτηκαν χρήματα για την παραγγελία %s', $releasedOrder->order_number),
            app()->getLocale() === 'en'
                ? 'The protected amount is now on its way to your connected Stripe account.'
                : 'Το προστατευμένο ποσό οδεύει πλέον προς το συνδεδεμένο Stripe account σου.',
            ['order_id' => $releasedOrder->getKey()],
            'orders'
        );

        $this->notifications->createForUser(
            (int) $releasedOrder->buyer_id,
            'order_completed',
            app()->getLocale() === 'en'
                ? sprintf('Order %s is complete', $releasedOrder->order_number)
                : sprintf('Η παραγγελία %s ολοκληρώθηκε', $releasedOrder->order_number),
            app()->getLocale() === 'en'
                ? 'The seller release completed and the order is now closed.'
                : 'Η αποδέσμευση προς τον πωλητή ολοκληρώθηκε και η παραγγελία έκλεισε.',
            ['order_id' => $releasedOrder->getKey()],
            'orders'
        );

        if ($releasedOrder->seller) {
            $this->notifications->sendEmailIfAllowed(
                $releasedOrder->seller,
                new MarketplaceEventMail(
                    $releasedOrder->seller,
                    $this->releaseMailContent($releasedOrder->seller->locale, 'seller', $releasedOrder)
                ),
                'orders'
            );
        }

        if ($releasedOrder->buyer) {
            $this->notifications->sendEmailIfAllowed(
                $releasedOrder->buyer,
                new MarketplaceEventMail(
                    $releasedOrder->buyer,
                    $this->releaseMailContent($releasedOrder->buyer->locale, 'buyer', $releasedOrder)
                ),
                'orders'
            );
        }

        return $releasedOrder;
    }

    public function createTransferToSeller(Order $order): object
    {
        $order = $this->resolveOrderChargeReference($order->loadMissing(['seller.sellerPayoutAccount', 'escrowTransaction']));
        $payoutAccount = $order->seller?->sellerPayoutAccount;

        if (! $payoutAccount?->stripe_account_id) {
            throw ValidationException::withMessages([
                'seller' => ['The seller does not have a Stripe connected account.'],
            ]);
        }

        if (! $order->stripe_charge_id) {
            throw ValidationException::withMessages([
                'stripe' => [
                    app()->getLocale() === 'el'
                        ? 'Η πληρωμή έχει καταγραφεί, αλλά η Stripe δεν έχει ακόμη επιστρέψει το source charge για ασφαλή αποδέσμευση. Δοκίμασε ξανά σε λίγα δευτερόλεπτα.'
                        : 'The payment is captured, but Stripe has not returned the source charge for a safe release yet. Please try again in a few seconds.',
                ],
            ]);
        }

        $payload = [
            'amount' => $this->toStripeAmount((float) $order->seller_amount),
            'currency' => strtolower($order->currency),
            'destination' => $payoutAccount->stripe_account_id,
            'transfer_group' => $this->transferGroup($order),
            'metadata' => $this->stripeMetadataForOrder($order),
        ];

        if ($order->stripe_charge_id) {
            $payload['source_transaction'] = $order->stripe_charge_id;
        }

        try {
            return $this->stripe()->transfers->create(
                $payload,
                [
                    'idempotency_key' => $this->releaseTransferIdempotencyKey($order, $payoutAccount->stripe_account_id),
                ]
            );
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe transfer creation failed for order release.', [
                'order_id' => $order->getKey(),
                'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                'stripe_charge_id' => $order->stripe_charge_id,
                'error' => $exception->getMessage(),
            ]);

            if (Str::contains(Str::lower($exception->getMessage()), 'insufficient available funds')) {
                throw ValidationException::withMessages([
                    'stripe' => [
                        app()->getLocale() === 'el'
                            ? 'Η πληρωμή υπάρχει κανονικά, αλλά η Stripe δεν έχει διαθέσιμο source balance για την αποδέσμευση αυτή τη στιγμή. Η παραγγελία παραμένει σε hold και μπορείς να ξαναδοκιμάσεις λίγο αργότερα.'
                            : 'The payment exists, but Stripe does not currently have available source balance for this release. The order remains on hold and you can try again shortly.',
                    ],
                ]);
            }

            throw $exception;
        }
    }

    public function retrieveBalanceForConnectedAccount(string $stripeAccountId): array
    {
        $balance = $this->stripe()->balance->retrieve([], [
            'stripe_account' => $stripeAccountId,
        ]);

        $normalizeRows = function (iterable $rows): array {
            $normalized = [];

            foreach ($rows as $row) {
                $amount = is_array($row) ? ($row['amount'] ?? 0) : ($row->amount ?? 0);
                $currency = is_array($row) ? ($row['currency'] ?? null) : ($row->currency ?? null);

                $normalized[] = [
                    'amount' => $this->stripeAmountToDecimal((int) $amount),
                    'currency' => strtoupper((string) $currency),
                ];
            }

            return $normalized;
        };

        return [
            'available' => $normalizeRows($balance->available ?? []),
            'pending' => $normalizeRows($balance->pending ?? []),
        ];
    }

    public function createPayoutIfNeeded(SellerPayoutAccount|User $seller, ?int $amount = null, ?string $currency = null): object
    {
        $payoutAccount = $seller instanceof User
            ? $seller->sellerPayoutAccount
            : $seller;

        if (! $payoutAccount?->stripe_account_id) {
            throw ValidationException::withMessages([
                'seller' => ['No Stripe connected account is available for payouts.'],
            ]);
        }

        $currency = strtolower($currency ?: $payoutAccount->default_currency ?: 'eur');
        $payload = ['currency' => $currency];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        return $this->stripe()->payouts->create(
            $payload,
            [
                'stripe_account' => $payoutAccount->stripe_account_id,
                'idempotency_key' => sprintf('connected_payout_%s_%s', $payoutAccount->stripe_account_id, $amount ?? 'auto'),
            ]
        );
    }

    public function refundOrder(Order $order, array $context = []): Order
    {
        $order->refresh();

        if ($order->status === OrderStatus::Refunded->value) {
            return $order->fresh(['buyer', 'seller', 'items']);
        }

        if ($order->isReleased()) {
            $this->ledgerService->recordPostReleaseRefundAlert($order, [
                'reason' => Arr::get($context, 'reason'),
                'requested_by' => Arr::get($context, 'requested_by'),
            ]);

            throw ValidationException::withMessages([
                'order' => ['This order has already been released to the seller. Handle the refund through a post-release recovery flow.'],
            ]);
        }

        if (! $order->stripe_payment_intent_id && ! $order->stripe_charge_id) {
            throw ValidationException::withMessages([
                'payment' => ['No Stripe payment reference is stored for this order.'],
            ]);
        }

        $refundPayload = [
            'reason' => 'requested_by_customer',
            'metadata' => array_filter([
                'order_id' => (string) $order->getKey(),
                'order_number' => (string) $order->order_number,
                'refund_reason' => (string) Arr::get($context, 'reason', ''),
            ]),
        ];

        if ($order->stripe_payment_intent_id) {
            $refundPayload['payment_intent'] = $order->stripe_payment_intent_id;
        } else {
            $refundPayload['charge'] = $order->stripe_charge_id;
        }

        $refund = $this->stripe()->refunds->create(
            $refundPayload,
            [
                'idempotency_key' => sprintf('refund_order_%s', $order->getKey()),
            ]
        );

        $refundedOrder = $this->checkoutService->markOrderRefundedBeforeRelease($order, [
            'stripe_refund_id' => $refund->id,
            'refund_status' => $refund->status,
            'reason' => Arr::get($context, 'reason'),
            'requested_by' => Arr::get($context, 'requested_by'),
        ]);

        $this->ledgerService->reversePendingFundsForRefund($refundedOrder, [
            'stripe_refund_id' => $refund->id,
            'reason' => Arr::get($context, 'reason'),
            'requested_by' => Arr::get($context, 'requested_by'),
        ]);
        $this->platformBalanceService->syncHeldFundsReserve();

        return $refundedOrder->fresh(['buyer', 'seller', 'items']);
    }

    public function markPaymentFailed(Order $order, array $context = []): Order
    {
        if ($order->status !== OrderStatus::PendingPayment->value) {
            return $order->fresh(['items', 'buyer', 'seller']);
        }

        return $this->checkoutService->cancelPendingOrder($order, [
            'payment_failure' => $context,
        ]);
    }

    public function handleCheckoutSessionCompleted(object $session): ?Order
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return null;
        }

        $order = $this->findOrderByStripeMetadata((array) ($session->metadata ?? []), $session->payment_intent ?? null, $session->id ?? null);

        if (! $order) {
            Log::warning('Stripe checkout.session.completed received without a matching order.', [
                'session_id' => $session->id ?? null,
                'payment_intent' => $session->payment_intent ?? null,
                'metadata' => $session->metadata ?? [],
            ]);

            return null;
        }

        return $this->confirmSuccessfulPayment($order, [
            'stripe_checkout_session_id' => $session->id ?? null,
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
            'payment_status' => $session->payment_status ?? null,
        ]);
    }

    public function handlePaymentIntentSucceeded(object $paymentIntent): ?Order
    {
        $order = $this->findOrderByStripeMetadata((array) ($paymentIntent->metadata ?? []), $paymentIntent->id ?? null);

        if (! $order) {
            Log::warning('Stripe payment_intent.succeeded received without a matching order.', [
                'payment_intent_id' => $paymentIntent->id ?? null,
                'metadata' => $paymentIntent->metadata ?? [],
            ]);

            return null;
        }

        $charge = $paymentIntent->charges->data[0] ?? null;
        $chargeId = $charge->id ?? ($paymentIntent->latest_charge ?? null);

        return $this->confirmSuccessfulPayment($order, [
            'stripe_payment_intent_id' => $paymentIntent->id ?? null,
            'stripe_charge_id' => is_string($chargeId) ? $chargeId : null,
            'payment_status' => $paymentIntent->status ?? null,
        ]);
    }

    protected function buildCheckoutLineItems(Order $order): array
    {
        $lineItems = [];

        foreach ($order->items as $item) {
            $title = $item->title_snapshot ?: ($item->drawCampaign?->title ?: $item->listing?->product?->title ?: 'Cardora order item');
            $description = $item->draw_campaign_id
                ? 'Cardora raffle entry'
                : ($item->listing?->product?->category?->name ?? 'Marketplace item');

            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'product_data' => [
                        'name' => $title,
                        'description' => $description,
                        'metadata' => array_filter([
                            'order_id' => (string) $order->getKey(),
                            'order_item_id' => (string) $item->getKey(),
                            'listing_id' => $item->listing_id ? (string) $item->listing_id : null,
                            'draw_campaign_id' => $item->draw_campaign_id ? (string) $item->draw_campaign_id : null,
                        ]),
                    ],
                    'unit_amount' => $this->toStripeAmount((float) $item->unit_price),
                ],
                'quantity' => (int) $item->quantity,
            ];
        }

        if ((float) $order->shipping_total > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'product_data' => [
                        'name' => 'Shipping',
                        'description' => 'Shipping, handling and Cardora protection included in checkout total.',
                    ],
                    'unit_amount' => $this->toStripeAmount((float) $order->shipping_total),
                ],
                'quantity' => 1,
            ];
        }

        if ((float) $order->service_fee > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'product_data' => [
                        'name' => 'Cardora buyer protection fee',
                        'description' => 'Marketplace buyer fee for protected checkout and order support.',
                    ],
                    'unit_amount' => $this->toStripeAmount((float) $order->service_fee),
                ],
                'quantity' => 1,
            ];
        }

        return $lineItems;
    }

    protected function stripeMetadataForOrder(Order $order): array
    {
        return [
            'order_id' => (string) $order->getKey(),
            'order_number' => (string) $order->order_number,
            'buyer_id' => (string) $order->buyer_id,
            'seller_id' => (string) $order->seller_id,
            'product_net_amount' => number_format((float) $order->subtotal, 2, '.', ''),
            'platform_wallet_amount' => number_format((float) $order->commission_amount, 2, '.', ''),
            'held_amount' => number_format((float) $order->seller_amount, 2, '.', ''),
            'buyer_fee_amount' => number_format((float) $order->service_fee, 2, '.', ''),
        ];
    }

    protected function findOrderByStripeMetadata(array $metadata, ?string $paymentIntentId = null, ?string $sessionId = null): ?Order
    {
        $orderId = isset($metadata['order_id']) ? (int) $metadata['order_id'] : null;

        if ($orderId) {
            return Order::query()->find($orderId);
        }

        if (! $paymentIntentId && ! $sessionId) {
            return null;
        }

        return Order::query()
            ->where(function ($query) use ($paymentIntentId, $sessionId) {
                if ($paymentIntentId) {
                    $query->where('stripe_payment_intent_id', $paymentIntentId);
                }

                if ($sessionId) {
                    $method = $paymentIntentId ? 'orWhere' : 'where';
                    $query->{$method}('stripe_checkout_session_id', $sessionId);
                }
            })
            ->first();
    }

    protected function transferGroup(Order $order): string
    {
        return sprintf('order_%s', $order->getKey());
    }

    protected function syncOrderPaymentReferences(Order $order, array $paymentPayload = []): Order
    {
        return DB::transaction(function () use ($order, $paymentPayload) {
            $lockedOrder = Order::query()
                ->with(['escrowTransaction'])
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $updates = array_filter([
                'stripe_checkout_session_id' => $paymentPayload['stripe_checkout_session_id'] ?? null,
                'stripe_payment_intent_id' => $paymentPayload['stripe_payment_intent_id'] ?? null,
                'stripe_charge_id' => $paymentPayload['stripe_charge_id'] ?? null,
                'payment_method' => $paymentPayload['payment_method'] ?? null,
            ], fn ($value) => is_string($value) ? trim($value) !== '' : $value !== null);

            if ($updates !== []) {
                $lockedOrder->forceFill(array_merge($updates, [
                    'metadata' => $this->mergeStripeMetadata($lockedOrder->metadata, $updates),
                ]))->save();

                $lockedOrder->escrowTransaction?->update([
                    'provider_reference' => $updates['stripe_payment_intent_id']
                        ?? $updates['stripe_charge_id']
                        ?? $lockedOrder->escrowTransaction?->provider_reference,
                    'metadata' => $this->mergeStripeMetadata($lockedOrder->escrowTransaction?->metadata, $updates),
                ]);
            }

            return $lockedOrder->fresh(['items', 'buyer', 'seller', 'escrowTransaction']);
        });
    }

    protected function resolveOrderChargeReference(Order $order): Order
    {
        if ($order->stripe_charge_id || ! $order->stripe_payment_intent_id) {
            return $order->fresh(['seller.sellerPayoutAccount', 'escrowTransaction']);
        }

        try {
            $paymentIntent = $this->stripe()->paymentIntents->retrieve($order->stripe_payment_intent_id, []);
        } catch (ApiErrorException $exception) {
            Log::warning('Unable to retrieve payment intent while resolving Stripe charge reference.', [
                'order_id' => $order->getKey(),
                'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                'error' => $exception->getMessage(),
            ]);

            return $order->fresh(['seller.sellerPayoutAccount', 'escrowTransaction']);
        }

        $chargeId = $paymentIntent->latest_charge ?? null;

        if (! is_string($chargeId) || trim($chargeId) === '') {
            $chargeId = $paymentIntent->charges->data[0]->id ?? null;
        }

        if (! is_string($chargeId) || trim($chargeId) === '') {
            return $order->fresh(['seller.sellerPayoutAccount', 'escrowTransaction']);
        }

        return $this->syncOrderPaymentReferences($order, [
            'stripe_payment_intent_id' => $paymentIntent->id ?? $order->stripe_payment_intent_id,
            'stripe_charge_id' => $chargeId,
        ])->fresh(['seller.sellerPayoutAccount', 'escrowTransaction']);
    }

    protected function mergeStripeMetadata(?array $base, array $updates): array
    {
        return array_filter(array_merge($base ?? [], [
            'stripe_checkout_session_id' => $updates['stripe_checkout_session_id'] ?? ($base['stripe_checkout_session_id'] ?? null),
            'stripe_payment_intent_id' => $updates['stripe_payment_intent_id'] ?? ($base['stripe_payment_intent_id'] ?? null),
            'stripe_charge_id' => $updates['stripe_charge_id'] ?? ($base['stripe_charge_id'] ?? null),
        ]), fn ($value) => $value !== null);
    }

    protected function releaseTransferIdempotencyKey(Order $order, string $destinationAccountId): string
    {
        return sprintf(
            'order_release_transfer_%s_%s_%s_%s',
            $order->getKey(),
            $order->stripe_charge_id ?: 'no_charge',
            $destinationAccountId,
            $this->toStripeAmount((float) $order->seller_amount),
        );
    }

    protected function checkoutSuccessUrl(Order $order): string
    {
        $base = rtrim((string) config('services.stripe.checkout_success_url'), '/');

        return sprintf(
            '%s?order=%s&order_id=%s&session_id={CHECKOUT_SESSION_ID}',
            $base,
            urlencode((string) $order->order_number),
            urlencode((string) $order->getKey()),
        );
    }

    protected function checkoutCancelUrl(Order $order): string
    {
        $base = rtrim((string) config('services.stripe.checkout_cancel_url'), '/');

        return sprintf(
            '%s?order=%s&order_id=%s&cancelled=1',
            $base,
            urlencode((string) $order->order_number),
            urlencode((string) $order->getKey()),
        );
    }

    protected function toStripeAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function stripeAmountToDecimal(int $amount): float
    {
        return round($amount / 100, 2);
    }

    protected function stripe(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return $this->client = new StripeClient($secret);
    }

    protected function releaseMailContent(?string $locale, string $audience, Order $order): array
    {
        $isEnglish = $locale === 'en';
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $sellerAmount = number_format((float) $order->seller_amount, 2, ',', '.').' '.strtoupper((string) $order->currency);

        if ($isEnglish) {
            if ($audience === 'seller') {
                return [
                    'subject' => sprintf('Funds released for order %s', $order->order_number),
                    'eyebrow' => 'Cardora orders',
                    'title' => 'Seller funds were released',
                    'body' => sprintf('Order %s completed and the seller amount moved toward your connected Stripe account.', $order->order_number),
                    'details' => [
                        ['label' => 'Order', 'value' => $order->order_number],
                        ['label' => 'Released amount', 'value' => $sellerAmount],
                    ],
                    'cta' => 'Open orders',
                    'url' => $frontendUrl.'/paraggelies',
                    'footer' => 'Payout timing is then handled by Stripe based on your connected account setup.',
                ];
            }

            return [
                'subject' => sprintf('Order %s is complete', $order->order_number),
                'eyebrow' => 'Cardora orders',
                'title' => 'Your protected order is complete',
                'body' => sprintf('Order %s completed and the seller release finished successfully.', $order->order_number),
                'details' => [
                    ['label' => 'Order', 'value' => $order->order_number],
                ],
                'cta' => 'Open orders',
                'url' => $frontendUrl.'/paraggelies',
                'footer' => 'Thanks for keeping confirmation and order history inside Cardora.',
            ];
        }

        if ($audience === 'seller') {
            return [
                'subject' => sprintf('Αποδεσμεύτηκαν χρήματα για την παραγγελία %s', $order->order_number),
                'eyebrow' => 'Παραγγελίες Cardora',
                'title' => 'Το ποσό του πωλητή αποδεσμεύτηκε',
                'body' => sprintf('Η παραγγελία %s ολοκληρώθηκε και το ποσό του πωλητή κινείται πλέον προς το συνδεδεμένο Stripe account σου.', $order->order_number),
                'details' => [
                    ['label' => 'Παραγγελία', 'value' => $order->order_number],
                    ['label' => 'Αποδεσμευμένο ποσό', 'value' => $sellerAmount],
                ],
                'cta' => 'Άνοιγμα παραγγελιών',
                'url' => $frontendUrl.'/paraggelies',
                'footer' => 'Ο τελικός χρόνος payout στη συνέχεια διαχειρίζεται από τη Stripe με βάση το connected account σου.',
            ];
        }

        return [
            'subject' => sprintf('Η παραγγελία %s ολοκληρώθηκε', $order->order_number),
            'eyebrow' => 'Παραγγελίες Cardora',
            'title' => 'Η protected παραγγελία σου ολοκληρώθηκε',
            'body' => sprintf('Η παραγγελία %s ολοκληρώθηκε και η αποδέσμευση προς τον πωλητή έγινε επιτυχώς.', $order->order_number),
            'details' => [
                ['label' => 'Παραγγελία', 'value' => $order->order_number],
            ],
            'cta' => 'Άνοιγμα παραγγελιών',
            'url' => $frontendUrl.'/paraggelies',
            'footer' => 'Σε ευχαριστούμε που κράτησες την επιβεβαίωση και το ιστορικό της παραγγελίας μέσα στην Cardora.',
        ];
    }
}
