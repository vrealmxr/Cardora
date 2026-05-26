<?php

namespace App\Services;

use App\Models\FeaturedListingPayment;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class FeaturedListingPaymentService
{
    protected ?StripeClient $client = null;

    public function createCheckout(User $user, ?Listing $listing = null): FeaturedListingPayment
    {
        $amount = (float) config('services.stripe.featured_listing_price', 2);
        $currency = strtoupper((string) config('services.stripe.featured_listing_currency', 'EUR'));
        $listingId = $listing?->getKey();

        $payment = FeaturedListingPayment::create([
            'listing_id' => $listingId,
            'user_id' => $user->getKey(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
        ]);

        $metadata = [
            'type' => 'featured_listing',
            'featured_payment_id' => (string) $payment->getKey(),
            'user_id' => (string) $user->getKey(),
        ];

        if ($listingId) {
            $metadata['listing_id'] = (string) $listingId;
        }

        $session = $this->stripe()->checkout->sessions->create(
            [
                'mode' => 'payment',
                'success_url' => $this->successUrl($listingId),
                'cancel_url' => $this->cancelUrl($listingId),
                'customer_email' => $user->email,
                'client_reference_id' => (string) $payment->getKey(),
                'metadata' => $metadata,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($currency),
                            'product_data' => [
                                'name' => 'Cardora Featured Listing',
                                'description' => 'Featured placement for 5 days.',
                            ],
                            'unit_amount' => $this->toStripeAmount($amount),
                        ],
                        'quantity' => 1,
                    ],
                ],
            ],
            [
                'idempotency_key' => sprintf('featured_listing_checkout_%s', $payment->getKey()),
            ]
        );

        $payment->forceFill([
            'stripe_checkout_session_id' => $session->id,
        ])->save();

        $payment->setRelation('checkout_session', $session);

        return $payment;
    }

    public function confirmSession(string $sessionId, ?User $user = null): ?FeaturedListingPayment
    {
        if (str_contains($sessionId, '{') || str_contains($sessionId, '}')) {
            Log::warning('Featured listing confirm received placeholder session id.', [
                'session_id' => $sessionId,
            ]);
            return null;
        }

        try {
            $session = $this->stripe()->checkout->sessions->retrieve($sessionId, []);
        } catch (ApiErrorException $exception) {
            Log::warning('Featured listing confirm failed to retrieve Stripe checkout session.', [
                'session_id' => $sessionId,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }

        if (($session->payment_status ?? null) !== 'paid') {
            return null;
        }

        $payment = FeaturedListingPayment::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->first();

        if (! $payment && ! empty($session->client_reference_id)) {
            $payment = FeaturedListingPayment::query()->find((int) $session->client_reference_id);
        }

        if (! $payment) {
            Log::warning('Featured listing payment not found for session.', [
                'session_id' => $sessionId,
            ]);
            return null;
        }

        if ($user && $payment->user_id !== $user->getKey()) {
            return null;
        }

        if ($payment->status === 'used') {
            return $payment;
        }

        if ($payment->status !== 'paid') {
            $durationDays = (int) config('services.stripe.featured_listing_duration_days', 5);
            $paidAt = CarbonImmutable::now();

            $payment->forceFill([
                'stripe_payment_intent_id' => $session->payment_intent ?? $payment->stripe_payment_intent_id,
                'status' => 'paid',
                'paid_at' => $paidAt,
                'expires_at' => $paidAt->addDays($durationDays),
            ])->save();
        }

        $this->attachKnownListing($payment, $session);

        return $payment;
    }

    public function attachPaymentToListing(FeaturedListingPayment $payment, Listing $listing): FeaturedListingPayment
    {
        if ($payment->status !== 'paid' || $payment->used_at) {
            return $payment;
        }

        $listing->forceFill([
            'is_featured' => true,
            'featured_until' => $payment->expires_at ?? now()->addDays((int) config('services.stripe.featured_listing_duration_days', 5)),
            'featured_payment_id' => $payment->getKey(),
        ])->save();

        $payment->forceFill([
            'listing_id' => $listing->getKey(),
            'status' => 'used',
            'used_at' => now(),
        ])->save();

        return $payment;
    }

    public function markPaidFromWebhook(object $session): ?FeaturedListingPayment
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return null;
        }

        $sessionId = $session->id ?? null;
        if (! $sessionId) {
            return null;
        }

        return $this->confirmSession($sessionId);
    }

    public function attachToSpecificListing(FeaturedListingPayment $payment, Listing $listing): FeaturedListingPayment
    {
        if ($payment->user_id !== $listing->seller_id) {
            return $payment;
        }

        if (! $payment->listing_id) {
            $payment->forceFill(['listing_id' => $listing->getKey()])->save();
        }

        if ($payment->status === 'paid' && ! $payment->used_at) {
            $this->attachPaymentToListing($payment, $listing);
        }

        return $payment->fresh() ?? $payment;
    }

    protected function attachKnownListing(FeaturedListingPayment $payment, object $session): void
    {
        $listingId = $payment->listing_id;

        if (! $listingId) {
            $listingId = isset($session->metadata->listing_id)
                ? (int) $session->metadata->listing_id
                : null;
        }

        if (! $listingId || $payment->status !== 'paid' || $payment->used_at) {
            return;
        }

        $listing = Listing::query()->find($listingId);
        if (! $listing || $listing->seller_id !== $payment->user_id) {
            return;
        }

        if ((int) $payment->listing_id !== (int) $listing->getKey()) {
            $payment->forceFill(['listing_id' => $listing->getKey()])->save();
        }

        $this->attachPaymentToListing($payment, $listing);
    }

    protected function successUrl(?int $listingId = null): string
    {
        $base = rtrim((string) config('services.stripe.featured_success_url', config('app.frontend_url', 'http://localhost:5173').'/oi-aggelies-mou'), '/');
        $params = [
            'featured' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ];

        if ($listingId) {
            $params['listing_id'] = (string) $listingId;
        }

        return $this->appendQueryParams($base, $params);
    }

    protected function cancelUrl(?int $listingId = null): string
    {
        $base = rtrim((string) config('services.stripe.featured_cancel_url', config('app.frontend_url', 'http://localhost:5173').'/oi-aggelies-mou'), '/');
        $params = ['featured' => 'cancelled'];

        if ($listingId) {
            $params['listing_id'] = (string) $listingId;
        }

        return $this->appendQueryParams($base, $params);
    }

    protected function toStripeAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function appendQueryParams(string $base, array $params): string
    {
        $glue = str_contains($base, '?') ? '&' : '?';
        $query = http_build_query($params, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);
        $query = str_replace(rawurlencode('{CHECKOUT_SESSION_ID}'), '{CHECKOUT_SESSION_ID}', $query);

        return $base.$glue.$query;
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
