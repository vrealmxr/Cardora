<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\FeaturedListingPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeaturedListingPaymentController extends Controller
{
    public function startCheckout(Request $request, FeaturedListingPaymentService $featuredService)
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'client' => ['nullable', 'string', 'in:ios,web'],
        ]);

        $listing = Listing::query()->findOrFail((int) $validated['listing_id']);
        abort_unless($listing->seller_id === $request->user()->getKey(), 403, __('api.errors.forbidden'));

        if ($listing->is_featured && $listing->featured_until && $listing->featured_until->isFuture()) {
            throw ValidationException::withMessages([
                'listing_id' => ['This listing is already featured.'],
            ]);
        }

        $client = $validated['client'] ?? 'web';
        $payment = $featuredService->createCheckout($request->user(), $listing, $client);

        return response()->json([
            'message' => 'Featured listing checkout session created.',
            'data' => [
                'featured_payment_id' => $payment->getKey(),
                'listing_id' => $listing->getKey(),
                'checkout_session_id' => $payment->stripe_checkout_session_id,
                'checkout_url' => $payment->checkout_session?->url,
                'app_return_url' => $client === 'ios',
            ],
        ], 201);
    }

    public function confirm(Request $request, FeaturedListingPaymentService $featuredService)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string'],
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
        ]);

        $payment = $featuredService->confirmSession($validated['session_id'], $request->user());

        if (! $payment) {
            return response()->json([
                'message' => 'Unable to confirm featured listing payment.',
            ], 422);
        }

        $targetListing = null;
        if (! empty($validated['listing_id'])) {
            $targetListing = Listing::query()
                ->where('id', (int) $validated['listing_id'])
                ->where('seller_id', $request->user()->getKey())
                ->first();
        } elseif ($payment->listing_id) {
            $targetListing = Listing::query()
                ->where('id', (int) $payment->listing_id)
                ->where('seller_id', $request->user()->getKey())
                ->first();
        }

        if ($targetListing && $payment->status === 'paid' && ! $payment->used_at) {
            $payment = $featuredService->attachToSpecificListing($payment, $targetListing);
        }

        return response()->json([
            'message' => 'Featured listing payment confirmed.',
            'data' => [
                'featured_payment_id' => $payment->getKey(),
                'status' => $payment->status,
                'expires_at' => optional($payment->expires_at)->toIso8601String(),
                'listing_id' => $payment->listing_id,
                'featured_until' => optional($targetListing?->fresh()?->featured_until)->toIso8601String(),
            ],
        ]);
    }
}
