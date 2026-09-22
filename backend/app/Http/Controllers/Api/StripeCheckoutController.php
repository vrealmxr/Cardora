<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\ListingOffer;
use App\Services\OrderCheckoutService;
use App\Services\StripeMarketplaceService;
use Illuminate\Http\Request;

class StripeCheckoutController extends Controller
{
    public function store(
        StartCheckoutRequest $request,
        OrderCheckoutService $checkoutService,
        StripeMarketplaceService $marketplaceService
    ) {
        $validated = $request->validated();

        // A private-offer checkout is always exactly one order; a cart checkout can now
        // produce several (one per cart item) — normalize both into a collection so a single
        // Stripe Checkout Session can be built to cover whichever it is.
        $orders = ! empty($validated['accepted_offer_id'])
            ? collect([$checkoutService->createPendingOrderForAcceptedOffer(
                $request->user(),
                ListingOffer::query()->findOrFail((int) $validated['accepted_offer_id']),
                $validated
            )])
            : $checkoutService->createPendingOrder($request->user(), $validated);
        $checkoutSession = $marketplaceService->createCheckoutSessionForOrders($orders);

        return response()->json([
            'message' => 'Stripe Checkout session created.',
            'data' => [
                'orders' => OrderResource::collection(
                    $orders->each->load(['items', 'buyer', 'seller', 'escrowTransaction'])
                ),
                'checkout_url' => $checkoutSession['checkout_url'],
                'checkout_session_id' => $checkoutSession['checkout_session_id'],
                'expires_at' => $checkoutSession['expires_at'],
            ],
        ], 201);
    }

    public function confirm(
        Request $request,
        StripeMarketplaceService $marketplaceService
    ) {
        $validated = $request->validate([
            'session_id' => ['required', 'string'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $orderId = isset($validated['order_id']) ? (int) $validated['order_id'] : null;

        $orders = $marketplaceService->confirmSession(
            $validated['session_id'],
            $request->user(),
            $orderId
        );

        if ($orders->isEmpty()) {
            return response()->json([
                'message' => 'Unable to confirm order payment from this Stripe session.',
            ], 422);
        }

        // Prefer the specifically-requested order for the singular `order` field (kept for the
        // current single-order success page); fall back to the batch's first order otherwise.
        $primaryOrder = $orderId
            ? ($orders->firstWhere('id', $orderId) ?? $orders->first())
            : $orders->first();

        return response()->json([
            'message' => 'Order payment confirmed.',
            'data' => [
                'order' => new OrderResource($primaryOrder->load(['items', 'buyer', 'seller', 'escrowTransaction'])),
                'orders' => OrderResource::collection(
                    $orders->each->load(['items', 'buyer', 'seller', 'escrowTransaction'])
                ),
            ],
        ]);
    }
}
