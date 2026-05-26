<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRefundOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\StripeMarketplaceService;
use Illuminate\Http\Request;

class AdminOrderPaymentController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $orders = Order::query()
            ->with(['buyer', 'seller', 'items', 'escrowTransaction'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 25));

        return OrderResource::collection($orders);
    }

    public function manualRelease(Request $request, Order $order, StripeMarketplaceService $marketplaceService)
    {
        $this->ensureAdmin($request);

        $releasedOrder = $marketplaceService->releaseFundsToSeller($order, [
            'buyer_confirmed' => false,
            'released_by' => sprintf('admin:%s', $request->user()->getKey()),
            'manual_release' => true,
        ]);

        return response()->json([
            'message' => 'Order funds released manually.',
            'data' => new OrderResource($releasedOrder->load(['buyer', 'seller', 'items', 'escrowTransaction'])),
        ]);
    }

    public function refund(
        AdminRefundOrderRequest $request,
        Order $order,
        StripeMarketplaceService $marketplaceService
    ) {
        $this->ensureAdmin($request);

        $refundedOrder = $marketplaceService->refundOrder($order, [
            'requested_by' => sprintf('admin:%s', $request->user()->getKey()),
            'reason' => $request->validated('reason'),
        ]);

        return response()->json([
            'message' => 'Order refunded successfully.',
            'data' => new OrderResource($refundedOrder->load(['buyer', 'seller', 'items', 'escrowTransaction'])),
        ]);
    }

    protected function ensureAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }
}
