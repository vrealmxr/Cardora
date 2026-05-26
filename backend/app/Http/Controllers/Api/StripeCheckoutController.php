<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\ListingOffer;
use App\Services\OrderCheckoutService;
use App\Services\StripeMarketplaceService;

class StripeCheckoutController extends Controller
{
    public function store(
        StartCheckoutRequest $request,
        OrderCheckoutService $checkoutService,
        StripeMarketplaceService $marketplaceService
    ) {
        $validated = $request->validated();

        $order = ! empty($validated['accepted_offer_id'])
            ? $checkoutService->createPendingOrderForAcceptedOffer(
                $request->user(),
                ListingOffer::query()->findOrFail((int) $validated['accepted_offer_id']),
                $validated
            )
            : $checkoutService->createPendingOrder($request->user(), $validated);
        $checkoutSession = $marketplaceService->createCheckoutSessionForOrder($order);

        return response()->json([
            'message' => 'Stripe Checkout session created.',
            'data' => [
                'order' => new OrderResource($order->load(['items', 'buyer', 'seller', 'escrowTransaction'])),
                'checkout_url' => $checkoutSession['checkout_url'],
                'checkout_session_id' => $checkoutSession['checkout_session_id'],
                'expires_at' => $checkoutSession['expires_at'],
            ],
        ], 201);
    }
}
