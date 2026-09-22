<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\StripeClient;

class CardoraProSubscriptionService
{
    protected ?StripeClient $client = null;

    /**
     * Create (or reuse) a Stripe Checkout Session for a Cardora PRO
     * subscription and return its URL. Mirrors FeaturedListingPaymentService's
     * shape but with mode: subscription and a one-time-only 7-day trial.
     */
    public function startCheckout(User $user): string
    {
        $priceId = (string) config('services.stripe.pro_price_id');
        if ($priceId === '') {
            throw new RuntimeException('Cardora PRO price is not configured (STRIPE_PRO_PRICE_ID).');
        }

        $customerId = $this->ensureStripeCustomer($user);

        $subscriptionData = [];
        if ($user->pro_trial_used_at === null) {
            $subscriptionData['trial_period_days'] = (int) config('services.stripe.pro_trial_days', 7);
        }

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'success_url' => $this->appendSessionId($this->localizedUrl('/cardora-pro/success')),
            'cancel_url' => $this->localizedUrl('/cardora-pro'),
            'client_reference_id' => (string) $user->getKey(),
            'metadata' => [
                'type' => 'cardora_pro_subscription',
                'user_id' => (string) $user->getKey(),
            ],
            'subscription_data' => array_merge($subscriptionData, [
                'metadata' => [
                    'type' => 'cardora_pro_subscription',
                    'user_id' => (string) $user->getKey(),
                ],
            ]),
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
        ]);

        return $session->url;
    }

    public function retrieveSubscription(string $stripeSubscriptionId): object
    {
        return $this->stripe()->subscriptions->retrieve($stripeSubscriptionId);
    }

    public function ensureStripeCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $this->stripe()->customers->create([
            'email' => $user->email,
            'name' => $user->display_name ?: $user->name,
            'metadata' => ['user_id' => (string) $user->getKey()],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * Upsert the local subscription record + the denormalized users.pro_*
     * read-path fields from a Stripe Subscription object. Called from every
     * subscription-lifecycle webhook so it's always the source of truth.
     */
    public function syncFromStripeSubscription(object $stripeSubscription): ?Subscription
    {
        $userId = $this->resolveUserId($stripeSubscription);
        if (! $userId) {
            Log::warning('Cardora PRO webhook: could not resolve user for subscription.', [
                'stripe_subscription_id' => $stripeSubscription->id ?? null,
            ]);

            return null;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return null;
        }

        // Newer Stripe API versions only echo current_period_start/end at
        // the top level on a fresh retrieve() — webhook event snapshots
        // carry them on the subscription item instead. Check both.
        $item = $stripeSubscription->items->data[0] ?? null;
        $priceId = $item->price->id ?? null;
        $periodStart = $stripeSubscription->current_period_start ?? $item->current_period_start ?? null;
        $periodEnd = $stripeSubscription->current_period_end ?? $item->current_period_end ?? null;
        $currentPeriodEnd = $this->toDateTime($periodEnd);
        $trialEnd = $this->toDateTime($stripeSubscription->trial_end ?? null);
        $cancelAtPeriodEnd = (bool) ($stripeSubscription->cancel_at_period_end ?? false);
        $status = (string) ($stripeSubscription->status ?? 'incomplete');
        $rawMetadata = $stripeSubscription->metadata ?? null;

        $subscription = Subscription::query()->updateOrCreate(
            ['stripe_subscription_id' => $stripeSubscription->id],
            [
                'user_id' => $user->getKey(),
                'stripe_price_id' => $priceId,
                'status' => $status,
                'trial_ends_at' => $trialEnd,
                'current_period_start' => $this->toDateTime($periodStart),
                'current_period_end' => $currentPeriodEnd,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'canceled_at' => $this->toDateTime($stripeSubscription->canceled_at ?? null),
                'ended_at' => $this->toDateTime($stripeSubscription->ended_at ?? null),
                'metadata' => $rawMetadata instanceof \Stripe\StripeObject
                    ? $rawMetadata->toArray()
                    : (is_array($rawMetadata) ? $rawMetadata : []),
            ]
        );

        $user->forceFill([
            'pro_status' => $status,
            'pro_current_period_end' => $currentPeriodEnd,
            'pro_cancel_at_period_end' => $cancelAtPeriodEnd,
        ])->save();

        return $subscription;
    }

    /**
     * Called the instant a trial-bearing checkout completes — not when the
     * trial ends — so the one-free-trial guard can't be bypassed by
     * cancelling the same day.
     */
    public function markTrialConsumed(User $user): void
    {
        if ($user->pro_trial_used_at !== null) {
            return;
        }

        $user->forceFill(['pro_trial_used_at' => now()])->save();
    }

    /**
     * Grant the monthly free featured-listing credit — called on every
     * invoice.paid for a PRO subscription (i.e. every successful billing
     * cycle, trial included once it converts).
     */
    public function grantFeaturedCredit(User $user): void
    {
        $user->forceFill(['pro_featured_credit_available' => true])->save();
    }

    public function cancelAtPeriodEnd(User $user): void
    {
        $subscriptionId = $this->activeStripeSubscriptionId($user);
        if (! $subscriptionId) {
            throw new RuntimeException('No active Cardora PRO subscription to cancel.');
        }

        $stripeSubscription = $this->stripe()->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);

        $this->syncFromStripeSubscription($stripeSubscription);
    }

    public function resume(User $user): void
    {
        $subscriptionId = $this->activeStripeSubscriptionId($user);
        if (! $subscriptionId) {
            throw new RuntimeException('No active Cardora PRO subscription to resume.');
        }

        $stripeSubscription = $this->stripe()->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => false,
        ]);

        $this->syncFromStripeSubscription($stripeSubscription);
    }

    protected function activeStripeSubscriptionId(User $user): ?string
    {
        return Subscription::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latest('id')
            ->value('stripe_subscription_id');
    }

    protected function resolveUserId(object $stripeSubscription): ?int
    {
        $metadataUserId = $stripeSubscription->metadata->user_id ?? null;
        if ($metadataUserId) {
            return (int) $metadataUserId;
        }

        $customerId = is_string($stripeSubscription->customer ?? null)
            ? $stripeSubscription->customer
            : ($stripeSubscription->customer->id ?? null);

        if (! $customerId) {
            return null;
        }

        return User::query()->where('stripe_customer_id', $customerId)->value('id');
    }

    protected function toDateTime(?int $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    /**
     * The Binder/Scanner/PRO pages live only under locale-prefixed routes
     * (/el/..., /en/...), unlike most of the marketplace which uses bare
     * paths — build the redirect URL accordingly.
     */
    protected function localizedUrl(string $path): string
    {
        $locale = in_array(app()->getLocale(), ['el', 'en'], true) ? app()->getLocale() : 'el';
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');

        return sprintf('%s/%s%s', $baseUrl, $locale, $path);
    }

    protected function appendSessionId(string $url): string
    {
        $glue = str_contains($url, '?') ? '&' : '?';

        return $url.$glue.'session_id={CHECKOUT_SESSION_ID}';
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
}
