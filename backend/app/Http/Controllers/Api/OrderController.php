<?php

namespace App\Http\Controllers\Api;

use App\Mail\MarketplaceEventMail;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\MarketplaceNotificationService;
use App\Services\OrderCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->getKey();
        $scope = $request->string('scope')->toString();

        $orders = Order::query()
            ->with($this->orderRelations())
            ->when(
                $scope === 'buyer',
                fn ($query) => $query->where('buyer_id', $userId),
                fn ($query) => $query->when(
                    $scope === 'seller',
                    fn ($sellerQuery) => $sellerQuery->where('seller_id', $userId),
                    fn ($allQuery) => $allQuery->where(function ($builder) use ($userId) {
                        $builder->where('buyer_id', $userId)->orWhere('seller_id', $userId);
                    })
                )
            )
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request, OrderCheckoutService $checkoutService)
    {
        $order = $checkoutService->placeOrder($request->user(), $request->validated());

        return response()->json([
            'message' => __('api.orders.created'),
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, Order $order)
    {
        $this->ensureParticipant($request, $order);

        return new OrderResource(
            $order->load($this->orderRelations())
        );
    }

    public function update(
        UpdateOrderStatusRequest $request,
        Order $order,
        MarketplaceNotificationService $notifications
    ) {
        $this->ensureParticipant($request, $order);

        $validated = $request->validated();
        $isBuyer = (int) $request->user()->getKey() === (int) $order->buyer_id;
        $isSeller = (int) $request->user()->getKey() === (int) $order->seller_id;

        if (array_key_exists('tracking_number', $validated) && ! $isSeller) {
            throw ValidationException::withMessages([
                'tracking_number' => ['Only the seller can update tracking for this order.'],
            ]);
        }

        if (($validated['status'] ?? null) === 'released') {
            throw ValidationException::withMessages([
                'status' => ['Use the protected confirmation flow to release seller funds.'],
            ]);
        }

        if (($validated['status'] ?? null) === 'released' && ! array_key_exists('completed_at', $validated)) {
            $validated['completed_at'] = now();
        }

        if (($validated['status'] ?? null) === 'disputed' && ! array_key_exists('disputed_at', $validated)) {
            $validated['disputed_at'] = now();
        }

        if (! empty($validated['tracking_number']) && $isSeller) {
            $validated['metadata'] = array_merge($order->metadata ?? [], $validated['metadata'] ?? [], [
                'shipped_at' => data_get($order->metadata, 'shipped_at') ?: now()->toIso8601String(),
            ]);
        }

        $order->update($validated);

        $recipientId = $request->user()->getKey() === $order->buyer_id ? $order->seller_id : $order->buyer_id;
        $notifications->createForUser(
            $recipientId,
            'order_updated',
            __('api.notifications.order_created_title', ['order' => $order->order_number]),
            __('api.orders.updated'),
            ['order_id' => $order->getKey()],
            'orders'
        );

        $recipient = (int) $recipientId === (int) $order->buyer_id ? $order->buyer : $order->seller;

        if ($recipient) {
            $notifications->sendEmailIfAllowed(
                $recipient,
                new MarketplaceEventMail(
                    $recipient,
                    $this->orderUpdateMailContent(
                        $recipient->locale,
                        (string) $order->order_number,
                        (string) ($validated['status'] ?? $order->status)
                    )
                ),
                'orders'
            );
        }

        return response()->json([
            'message' => __('api.orders.updated'),
            'data' => new OrderResource(
                $order->fresh()->load($this->orderRelations())
            ),
        ]);
    }

    public function destroy(Request $request, Order $order)
    {
        $this->ensureParticipant($request, $order);

        if (! in_array($order->status, ['pending_payment', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'order' => [__('api.errors.forbidden')],
            ]);
        }

        DB::transaction(function () use ($order) {
            $order->load('items.listing');

            foreach ($order->items as $item) {
                if (! $item->listing) {
                    continue;
                }

                $currentAvailable = (int) ($item->listing->available_quantity ?? 0);
                $restoredQuantity = $currentAvailable + (int) $item->quantity;

                $item->listing->update([
                    'available_quantity' => $restoredQuantity,
                    'status' => 'active',
                    'availability' => 'available',
                ]);
            }

            $order->escrowTransaction()?->delete();
            $order->delete();
        });

        return response()->noContent();
    }

    protected function ensureParticipant(Request $request, Order $order): void
    {
        abort_unless(
            in_array($request->user()->getKey(), [$order->buyer_id, $order->seller_id], true),
            403,
            __('api.errors.forbidden')
        );
    }

    protected function orderRelations(): array
    {
        return [
            'buyer',
            'seller',
            'items.listing.product.category',
            'items.product.category',
            'items.drawCampaign.hostUser',
            'escrowTransaction',
        ];
    }

    protected function orderUpdateMailContent(?string $locale, string $orderNumber, string $status): array
    {
        $isEnglish = $locale === 'en';
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

        if ($isEnglish) {
            return [
                'subject' => sprintf('Order %s was updated on Cardora', $orderNumber),
                'eyebrow' => 'Cardora orders',
                'title' => 'An order update is available',
                'body' => sprintf('Order %s changed status to %s.', $orderNumber, $status),
                'details' => [
                    ['label' => 'Order', 'value' => $orderNumber],
                    ['label' => 'Status', 'value' => $status],
                ],
                'cta' => 'Open orders',
                'url' => $frontendUrl.'/paraggelies',
                'footer' => 'You are receiving this because order email notifications are enabled in your Cardora account.',
            ];
        }

        return [
            'subject' => sprintf('Η παραγγελία %s ενημερώθηκε στην Cardora', $orderNumber),
            'eyebrow' => 'Παραγγελίες Cardora',
            'title' => 'Υπάρχει νέα ενημέρωση παραγγελίας',
            'body' => sprintf('Η παραγγελία %s άλλαξε κατάσταση σε %s.', $orderNumber, $status),
            'details' => [
                ['label' => 'Παραγγελία', 'value' => $orderNumber],
                ['label' => 'Κατάσταση', 'value' => $status],
            ],
            'cta' => 'Άνοιγμα παραγγελιών',
            'url' => $frontendUrl.'/paraggelies',
            'footer' => 'Λαμβάνεις αυτό το email επειδή οι ειδοποιήσεις παραγγελιών μέσω email είναι ενεργές στον λογαριασμό σου.',
        ];
    }
}
