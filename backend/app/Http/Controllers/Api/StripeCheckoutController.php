<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
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

    public function confirm(
        Request $request,
        StripeMarketplaceService $marketplaceService
    ) {
        $validated = $request->validate([
            'session_id' => ['required', 'string'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $order = $marketplaceService->confirmSession(
            $validated['session_id'],
            $request->user(),
            isset($validated['order_id']) ? (int) $validated['order_id'] : null
        );

        if (! $order) {
            return response()->json([
                'message' => 'Unable to confirm order payment from this Stripe session.',
            ], 422);
        }

        if (
            ! empty($validated['order_id'])
            && (int) $order->getKey() !== (int) $validated['order_id']
        ) {
            $requestedOrder = Order::query()
                ->whereKey((int) $validated['order_id'])
                ->where('buyer_id', $request->user()->getKey())
                ->first();

            if (! $requestedOrder) {
                abort(403, __('api.errors.forbidden'));
            }
        }

        return response()->json([
            'message' => 'Order payment confirmed.',
            'data' => [
                'order' => new OrderResource($order->load(['items', 'buyer', 'seller', 'escrowTransaction'])),
            ],
        ]);
    }
}
