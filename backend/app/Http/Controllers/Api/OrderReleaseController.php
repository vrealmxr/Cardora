<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\StripeMarketplaceService;
use Illuminate\Http\Request;

class OrderReleaseController extends Controller
{
    public function confirmReceived(Request $request, Order $order, StripeMarketplaceService $marketplaceService)
    {
        $this->authorize('confirmReceived', $order);

        $releasedOrder = $marketplaceService->releaseFundsToSeller($order, [
            'buyer_confirmed' => true,
            'confirmed_at' => now(),
            'released_by' => sprintf('buyer:%s', $request->user()->getKey()),
        ]);

        return response()->json([
            'message' => 'Delivery confirmed and seller funds released.',
            'data' => new OrderResource($releasedOrder->load(['items', 'buyer', 'seller', 'escrowTransaction'])),
        ]);
    }
}
