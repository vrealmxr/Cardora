<?php

namespace App\Services;

use App\Mail\MarketplaceEventMail;
use App\Enums\OrderStatus;
use App\Models\CartItem;
use App\Models\DrawCampaign;
use App\Models\DrawEntry;
use App\Models\EscrowTransaction;
use App\Models\Listing;
use App\Models\ListingOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\MarketplaceSellerFeeCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderCheckoutService
{
    public function __construct(
        protected MarketplaceNotificationService $notifications,
        protected DrawEntryService $drawEntries,
        protected CartSanitizerService $cartSanitizer,
        protected MarketplaceAccessService $marketplaceAccess,
        protected LotCardSelectionService $lotCardSelections,
        protected ListingOfferService $listingOffers
    ) {
    }

    public function placeOrder(User $buyer, array $payload): Order
    {
        return $this->createPendingOrder($buyer, $payload);
    }

    public function createPendingOrder(User $buyer, array $payload): Order
    {
        $this->marketplaceAccess->assertCanBuy($buyer, app()->getLocale());

        return DB::transaction(function () use ($buyer, $payload) {
            $lineItems = $this->resolveLineItems($buyer, $payload);
            $sellerId = (int) $lineItems->first()['seller_id'];

            if ($lineItems->contains(fn (array $item) => (int) $item['seller_id'] === (int) $buyer->getKey())) {
                throw ValidationException::withMessages([
                    'items' => [__('api.orders.self_checkout_not_allowed')],
                ]);
            }

            if ($lineItems->pluck('seller_id')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'items' => [__('api.orders.invalid_items')],
                ]);
            }

            $subtotal = round(
                $lineItems->sum(fn (array $item) => $item['unit_price'] * $item['quantity']),
                2
            );
            $shippingTotal = $this->calculateShippingTotal($lineItems, $payload);
            $commissionAmount = $this->calculateCommissionAmount($subtotal);
            $buyerFeeAmount = $this->calculateBuyerFeeAmount($subtotal);
            $sellerAmount = round(($subtotal + $shippingTotal) - $commissionAmount, 2);
            $total = round($subtotal + $shippingTotal + $buyerFeeAmount, 2);
            $primaryProductId = $lineItems->pluck('product')->filter()->first()?->getKey();
            $shippingCarrier = $this->resolveShippingCarrier($lineItems);
            $shippingService = data_get($payload, 'shipping_address.delivery_type', 'home_delivery');

            $order = Order::create([
                'buyer_id' => $buyer->getKey(),
                'seller_id' => $sellerId,
                'product_id' => $primaryProductId,
                'order_number' => $payload['order_number'] ?? $this->generateOrderNumber(),
                'status' => OrderStatus::PendingPayment->value,
                'escrow_status' => OrderStatus::PendingPayment->value,
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'service_fee' => $buyerFeeAmount,
                'total' => $total,
                'total_amount' => $total,
                'commission_amount' => $commissionAmount,
                'seller_amount' => $sellerAmount,
                'currency' => strtoupper((string) ($payload['currency'] ?? 'EUR')),
                'payment_method' => 'stripe_checkout',
                'shipping_carrier' => $shippingCarrier,
                'shipping_service' => $shippingCarrier === 'dhl_express' ? $shippingService : null,
                'shipment_status' => $shippingCarrier === 'dhl_express' ? 'pending_label' : null,
                'shipping_address' => $payload['shipping_address'] ?? null,
                'billing_address' => $payload['billing_address'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'placed_at' => $payload['placed_at'] ?? now(),
                'auto_release_at' => null,
                'metadata' => $this->mergeMetadata($payload['metadata'] ?? null, [
                    'source' => $lineItems->contains(fn (array $item) => $item['cart_item_id'] !== null)
                        ? 'cart_checkout'
                        : 'direct_checkout',
                    'payment_integration' => 'stripe_connect_separate_charges_and_transfers',
                    'cart_item_ids' => $lineItems->pluck('cart_item_id')->filter()->values()->all(),
                ]),
            ]);

            foreach ($lineItems as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->getKey(),
                    'listing_id' => $item['listing']?->getKey(),
                    'draw_campaign_id' => $item['draw_campaign']?->getKey(),
                    'product_id' => $item['product']?->getKey(),
                    'title_snapshot' => $item['title'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'condition_snapshot' => $item['condition_snapshot'],
                    'metadata' => array_merge($item['metadata'], [
                        'cart_item_id' => $item['cart_item_id'],
                    ]),
                ]);

                if ($item['item_type'] !== 'listing') {
                    continue;
                }

                if (($item['metadata']['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE) {
                    $this->lotCardSelections->reserveForOrderItem($orderItem);

                    continue;
                }

                $remainingQuantity = max(
                    0,
                    (int) ($item['listing']->available_quantity ?? $item['listing']->quantity ?? 0) - $item['quantity']
                );

                $item['listing']->update([
                    'available_quantity' => $remainingQuantity,
                    'status' => $remainingQuantity === 0 ? 'sold' : $item['listing']->status,
                    'availability' => $remainingQuantity === 0 ? 'sold_out' : $item['listing']->availability,
                ]);
            }

            EscrowTransaction::create([
                'order_id' => $order->getKey(),
                'buyer_id' => $buyer->getKey(),
                'seller_id' => $sellerId,
                'amount' => $total,
                'currency' => $order->currency,
                'status' => OrderStatus::PendingPayment->value,
                'metadata' => [
                    'payment_integration' => 'stripe_connect_separate_charges_and_transfers',
                    'product_net_amount' => $subtotal,
                    'commission_amount' => $commissionAmount,
                    'platform_wallet_amount' => $commissionAmount,
                    'buyer_fee_amount' => $buyerFeeAmount,
                    'held_amount' => $sellerAmount,
                    'seller_amount' => $sellerAmount,
                ],
            ]);

            return $order->load($this->orderRelations());
        });
    }

    public function createPendingOrderForAcceptedOffer(User $buyer, ListingOffer $offer, array $payload): Order
    {
        $this->marketplaceAccess->assertCanBuy($buyer, app()->getLocale());
        $this->listingOffers->assertBuyerCanCheckout($offer, $buyer);

        return DB::transaction(function () use ($buyer, $offer, $payload) {
            $lockedOffer = ListingOffer::query()
                ->with(['listing.product.category', 'listing.seller', 'conversation'])
                ->lockForUpdate()
                ->findOrFail($offer->getKey());

            $this->listingOffers->assertBuyerCanCheckout($lockedOffer, $buyer);

            $linkedOrder = $lockedOffer->order;
            if ($linkedOrder && $linkedOrder->isPendingPayment()) {
                return $linkedOrder->load($this->orderRelations());
            }

            $listing = $lockedOffer->listing;
            $lineItem = [
                'cart_item_id' => null,
                'item_type' => 'listing',
                'seller_id' => (int) $listing->seller_id,
                'listing' => $listing,
                'draw_campaign' => null,
                'product' => $listing->product,
                'quantity' => 1,
                'unit_price' => (float) $lockedOffer->item_amount,
                'title' => $listing->title_snapshot ?: $listing->product?->title ?: __('api.orders.untitled_item'),
                'condition_snapshot' => $listing->condition,
                'metadata' => [
                    'item_type' => 'listing',
                    'sale_format' => $listing->sale_format,
                    'rarity' => $listing->rarity,
                    'lot_snapshot' => $listing->lot_snapshot,
                    'private_offer_id' => $lockedOffer->getKey(),
                    'private_offer_total' => (float) $lockedOffer->total_amount,
                    'private_offer_shipping' => (float) $lockedOffer->shipping_amount,
                ],
            ];

            $commissionAmount = round((float) $lockedOffer->commission_amount, 2);
            $shippingTotal = round((float) $lockedOffer->shipping_amount, 2);
            $subtotal = round((float) $lockedOffer->item_amount, 2);
            $agreedTotal = round((float) $lockedOffer->total_amount, 2);
            $buyerFeeAmount = $this->calculateBuyerFeeAmount($subtotal);
            $total = round($agreedTotal + $buyerFeeAmount, 2);
            $sellerAmount = round($agreedTotal - $commissionAmount, 2);
            $shippingCarrier = $this->resolveShippingCarrier(collect([$lineItem]));
            $shippingService = data_get($payload, 'shipping_address.delivery_type', 'home_delivery');

            $order = Order::create([
                'buyer_id' => $buyer->getKey(),
                'seller_id' => $listing->seller_id,
                'product_id' => $listing->product?->getKey(),
                'order_number' => $payload['order_number'] ?? $this->generateOrderNumber(),
                'status' => OrderStatus::PendingPayment->value,
                'escrow_status' => OrderStatus::PendingPayment->value,
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'service_fee' => $buyerFeeAmount,
                'total' => $total,
                'total_amount' => $total,
                'commission_amount' => $commissionAmount,
                'seller_amount' => $sellerAmount,
                'currency' => strtoupper((string) ($payload['currency'] ?? 'EUR')),
                'payment_method' => 'stripe_checkout',
                'shipping_carrier' => $shippingCarrier,
                'shipping_service' => $shippingCarrier === 'dhl_express' ? $shippingService : null,
                'shipment_status' => $shippingCarrier === 'dhl_express' ? 'pending_label' : null,
                'shipping_address' => $payload['shipping_address'] ?? null,
                'billing_address' => $payload['billing_address'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'placed_at' => $payload['placed_at'] ?? now(),
                'auto_release_at' => null,
                'metadata' => $this->mergeMetadata($payload['metadata'] ?? null, [
                    'source' => 'private_offer_checkout',
                    'private_offer_id' => $lockedOffer->getKey(),
                    'private_offer_sequence' => $lockedOffer->sequence,
                    'payment_integration' => 'stripe_connect_separate_charges_and_transfers',
                ]),
            ]);

            OrderItem::create([
                'order_id' => $order->getKey(),
                'listing_id' => $listing->getKey(),
                'draw_campaign_id' => null,
                'product_id' => $listing->product?->getKey(),
                'title_snapshot' => $lineItem['title'],
                'unit_price' => $subtotal,
                'quantity' => 1,
                'condition_snapshot' => $listing->condition,
                'metadata' => $lineItem['metadata'],
            ]);

            $remainingQuantity = max(
                0,
                (int) ($listing->available_quantity ?? $listing->quantity ?? 0) - 1
            );

            $listing->update([
                'available_quantity' => $remainingQuantity,
                'status' => $remainingQuantity === 0 ? 'sold' : $listing->status,
                'availability' => $remainingQuantity === 0 ? 'sold_out' : $listing->availability,
            ]);

            EscrowTransaction::create([
                'order_id' => $order->getKey(),
                'buyer_id' => $buyer->getKey(),
                'seller_id' => $listing->seller_id,
                'amount' => $total,
                'currency' => $order->currency,
                'status' => OrderStatus::PendingPayment->value,
                'metadata' => [
                    'payment_integration' => 'stripe_connect_separate_charges_and_transfers',
                    'product_net_amount' => $subtotal,
                    'commission_amount' => $commissionAmount,
                    'platform_wallet_amount' => $commissionAmount,
                    'buyer_fee_amount' => $buyerFeeAmount,
                    'held_amount' => $sellerAmount,
                    'seller_amount' => $sellerAmount,
                    'private_offer_id' => $lockedOffer->getKey(),
                    'private_offer_agreed_total' => $agreedTotal,
                ],
            ]);

            $this->listingOffers->attachOrder($lockedOffer, $order);

            return $order->load($this->orderRelations());
        });
    }

    public function finalizeSuccessfulPayment(Order $order, array $paymentPayload = []): Order
    {
        return DB::transaction(function () use ($order, $paymentPayload) {
            $lockedOrder = Order::query()
                ->with($this->orderRelations())
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if (! $lockedOrder->isPendingPayment()) {
                return $lockedOrder->load($this->orderRelations());
            }

            $lockedOrder->update([
                'status' => OrderStatus::PaidPendingRelease->value,
                'escrow_status' => OrderStatus::PaidPendingRelease->value,
                'stripe_checkout_session_id' => $paymentPayload['stripe_checkout_session_id'] ?? $lockedOrder->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $paymentPayload['stripe_payment_intent_id'] ?? $lockedOrder->stripe_payment_intent_id,
                'stripe_charge_id' => $paymentPayload['stripe_charge_id'] ?? $lockedOrder->stripe_charge_id,
                'payment_method' => $paymentPayload['payment_method'] ?? $lockedOrder->payment_method ?: 'stripe_checkout',
                'metadata' => $this->mergeMetadata($lockedOrder->metadata, [
                    'payment_status' => 'captured',
                    'stripe_checkout_session_id' => $paymentPayload['stripe_checkout_session_id'] ?? $lockedOrder->stripe_checkout_session_id,
                ]),
            ]);

            $escrow = $lockedOrder->escrowTransaction
                ?: EscrowTransaction::create([
                    'order_id' => $lockedOrder->getKey(),
                    'buyer_id' => $lockedOrder->buyer_id,
                    'seller_id' => $lockedOrder->seller_id,
                    'amount' => $lockedOrder->total_amount ?: $lockedOrder->total,
                    'currency' => $lockedOrder->currency,
                    'status' => OrderStatus::PaidPendingRelease->value,
                    'metadata' => [
                        'payment_integration' => 'stripe_connect_separate_charges_and_transfers',
                        'product_net_amount' => (float) $lockedOrder->subtotal,
                        'commission_amount' => (float) $lockedOrder->commission_amount,
                        'platform_wallet_amount' => (float) $lockedOrder->commission_amount,
                        'buyer_fee_amount' => (float) $lockedOrder->service_fee,
                        'held_amount' => (float) $lockedOrder->seller_amount,
                        'seller_amount' => (float) $lockedOrder->seller_amount,
                    ],
                ]);

            $escrow->update([
                'status' => OrderStatus::PaidPendingRelease->value,
                'provider_reference' => $paymentPayload['stripe_payment_intent_id']
                    ?? $paymentPayload['stripe_charge_id']
                    ?? $escrow->provider_reference,
                'held_at' => $escrow->held_at ?? now(),
                'metadata' => $this->mergeMetadata($escrow->metadata, [
                    'stripe_charge_id' => $paymentPayload['stripe_charge_id'] ?? null,
                    'stripe_payment_intent_id' => $paymentPayload['stripe_payment_intent_id'] ?? null,
                ]),
            ]);

            $existingDrawEntries = DrawEntry::query()
                ->where('order_id', $lockedOrder->getKey())
                ->exists();

            foreach ($lockedOrder->items as $item) {
                if ($item->listing && (($item->metadata['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE)) {
                    $this->lotCardSelections->finalizeOrderItem($item);
                }
            }

            if (! $existingDrawEntries) {
                foreach ($lockedOrder->items as $item) {
                    if (! $item->draw_campaign_id || ! $item->drawCampaign || ! $lockedOrder->buyer) {
                        continue;
                    }

                    $this->drawEntries->createConfirmedEntry(
                        $lockedOrder->buyer,
                        $item->drawCampaign,
                        (int) $item->quantity,
                        [
                            'order_id' => $lockedOrder->getKey(),
                            'amount' => round((float) $item->unit_price * (int) $item->quantity, 2),
                            'source_type' => 'ticket_purchase',
                            'source_reference' => $lockedOrder->order_number,
                            'metadata' => [
                                'order_item_id' => $item->getKey(),
                                'checkout_source' => data_get($item->metadata, 'cart_item_id') ? 'cart' : 'direct',
                            ],
                        ]
                    );
                }
            }

            $cartItemIds = $lockedOrder->items
                ->pluck('metadata.cart_item_id')
                ->filter()
                ->values()
                ->all();

            if ($cartItemIds !== [] && $lockedOrder->buyer) {
                $lockedOrder->buyer->cartItems()->whereIn('id', $cartItemIds)->delete();
            }

            if ($privateOfferId = data_get($lockedOrder->metadata, 'private_offer_id')) {
                $privateOffer = ListingOffer::query()->find((int) $privateOfferId);

                if ($privateOffer) {
                    $this->listingOffers->markPaid($privateOffer, $lockedOrder);
                }
            }

            $this->notifications->createForUser(
                $lockedOrder->seller_id,
                'order_received',
                __('api.notifications.order_received_title', ['order' => $lockedOrder->order_number]),
                __('api.notifications.order_received_body', ['buyer' => $lockedOrder->buyer?->display_name ?: $lockedOrder->buyer?->name]),
                ['order_id' => $lockedOrder->getKey()],
                'orders'
            );

            $this->notifications->createForUser(
                $lockedOrder->buyer_id,
                'order_created',
                __('api.notifications.order_created_title', ['order' => $lockedOrder->order_number]),
                __('api.notifications.order_created_body'),
                ['order_id' => $lockedOrder->getKey()],
                'orders'
            );

            if ($lockedOrder->seller) {
                $this->notifications->sendEmailIfAllowed(
                    $lockedOrder->seller,
                    new MarketplaceEventMail(
                        $lockedOrder->seller,
                        $this->orderMailContent($lockedOrder->seller->locale, 'seller_paid', $lockedOrder)
                    ),
                    'orders'
                );
            }

            if ($lockedOrder->buyer) {
                $this->notifications->sendEmailIfAllowed(
                    $lockedOrder->buyer,
                    new MarketplaceEventMail(
                        $lockedOrder->buyer,
                        $this->orderMailContent($lockedOrder->buyer->locale, 'buyer_paid', $lockedOrder)
                    ),
                    'orders'
                );
            }

            return $lockedOrder->fresh()->load($this->orderRelations());
        });
    }

    public function cancelPendingOrder(Order $order, array $context = [], bool $restoreInventory = true): Order
    {
        return DB::transaction(function () use ($order, $context, $restoreInventory) {
            $lockedOrder = Order::query()
                ->with(['items.listing', 'escrowTransaction'])
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if (! $lockedOrder->isPendingPayment()) {
                return $lockedOrder;
            }

            if ($restoreInventory) {
                $this->restoreReservedInventory($lockedOrder);
            }

            $lockedOrder->update([
                'status' => OrderStatus::Cancelled->value,
                'escrow_status' => OrderStatus::Cancelled->value,
                'cancelled_at' => now(),
                'metadata' => $this->mergeMetadata($lockedOrder->metadata, $context),
            ]);

            $lockedOrder->escrowTransaction?->update([
                'status' => OrderStatus::Cancelled->value,
                'resolved_at' => now(),
                'metadata' => $this->mergeMetadata($lockedOrder->escrowTransaction?->metadata, $context),
            ]);

            return $lockedOrder->fresh()->load($this->orderRelations());
        });
    }

    public function markOrderRefundedBeforeRelease(Order $order, array $context = []): Order
    {
        return DB::transaction(function () use ($order, $context) {
            $lockedOrder = Order::query()
                ->with(['items.listing', 'escrowTransaction'])
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $this->restoreReservedInventory($lockedOrder);

            $lockedOrder->update([
                'status' => OrderStatus::Refunded->value,
                'escrow_status' => OrderStatus::Refunded->value,
                'refunded_at' => now(),
                'metadata' => $this->mergeMetadata($lockedOrder->metadata, $context),
            ]);

            $lockedOrder->escrowTransaction?->update([
                'status' => OrderStatus::Refunded->value,
                'resolved_at' => now(),
                'metadata' => $this->mergeMetadata($lockedOrder->escrowTransaction?->metadata, $context),
            ]);

            return $lockedOrder->fresh()->load($this->orderRelations());
        });
    }

    protected function resolveLineItems(User $buyer, array $payload): Collection
    {
        $requestedItems = collect($payload['items'] ?? []);

        if ($requestedItems->isEmpty()) {
            $this->cartSanitizer->purgeInvalidItems($buyer);

            $cartItems = $buyer->cartItems()
                ->with(['listing.product.category', 'listing.seller', 'drawCampaign.hostUser'])
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [__('api.orders.empty_cart')],
                ]);
            }

            return $cartItems->map(function (CartItem $cartItem) use ($buyer): array {
                if ($cartItem->draw_campaign_id) {
                    if (! $cartItem->drawCampaign) {
                        throw ValidationException::withMessages([
                            'items' => [__('api.orders.invalid_items')],
                        ]);
                    }

                    return $this->buildDrawLineItem(
                        $cartItem->drawCampaign,
                        (int) $cartItem->quantity,
                        $buyer,
                        $cartItem->getKey()
                    );
                }

                if (! $cartItem->listing) {
                    throw ValidationException::withMessages([
                        'items' => [__('api.orders.invalid_items')],
                    ]);
                }

                return $this->buildListingLineItem(
                    $cartItem->listing,
                    (int) $cartItem->quantity,
                    $cartItem->getKey(),
                    $cartItem->metadata ?? []
                );
            });
        }

        $listingIds = $requestedItems
            ->pluck('listing_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $drawCampaignIds = $requestedItems
            ->pluck('draw_campaign_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $listings = Listing::query()
            ->with(['product.category', 'seller'])
            ->whereIn('id', $listingIds)
            ->get()
            ->keyBy('id');
        $drawCampaigns = DrawCampaign::query()
            ->with('hostUser')
            ->whereIn('id', $drawCampaignIds)
            ->get()
            ->keyBy('id');

        if ($listings->count() !== count($listingIds) || $drawCampaigns->count() !== count($drawCampaignIds)) {
            throw ValidationException::withMessages([
                'items' => [__('api.orders.invalid_items')],
            ]);
        }

        return $requestedItems->map(function (array $requestedItem) use ($buyer, $drawCampaigns, $listings): array {
            $quantity = (int) ($requestedItem['quantity'] ?? 1);

            if (! empty($requestedItem['draw_campaign_id'])) {
                $drawCampaign = $drawCampaigns->get((int) $requestedItem['draw_campaign_id']);

                return $this->buildDrawLineItem($drawCampaign, $quantity, $buyer);
            }

            $listing = $listings->get((int) $requestedItem['listing_id']);

            return $this->buildListingLineItem(
                $listing,
                $quantity,
                null,
                $requestedItem['metadata'] ?? []
            );
        });
    }

    protected function buildListingLineItem(
        Listing $listing,
        int $quantity,
        ?int $cartItemId = null,
        array $metadata = []
    ): array
    {
        if ($listing->sale_format === 'trade') {
            throw ValidationException::withMessages([
                'items' => ['Trade listings cannot be purchased through normal checkout. Use the trade flow.'],
            ]);
        }

        if ($listing->sale_format === 'auction') {
            throw ValidationException::withMessages([
                'items' => [__('api.orders.auction_checkout_not_supported')],
            ]);
        }

        if (($metadata['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE) {
            return $this->buildLotSelectionLineItem($listing, $metadata, $cartItemId);
        }

        $this->lotCardSelections->assertWholeLotPurchaseAllowed($listing);

        $availableQuantity = (int) ($listing->available_quantity ?? $listing->quantity ?? 0);

        if ($quantity < 1 || $quantity > $availableQuantity) {
            throw ValidationException::withMessages([
                'items' => [__('api.orders.insufficient_stock', [
                    'title' => $listing->title_snapshot ?: $listing->product?->title ?: __('api.orders.untitled_item'),
                ])],
            ]);
        }

        return [
            'cart_item_id' => $cartItemId,
            'item_type' => 'listing',
            'seller_id' => (int) $listing->seller_id,
            'listing' => $listing,
            'draw_campaign' => null,
            'product' => $listing->product,
            'quantity' => $quantity,
            'unit_price' => (float) $listing->price,
            'title' => $listing->title_snapshot ?: $listing->product?->title ?: __('api.orders.untitled_item'),
            'condition_snapshot' => $listing->condition,
            'metadata' => [
                'item_type' => 'listing',
                'sale_format' => $listing->sale_format,
                'rarity' => $listing->rarity,
                'lot_snapshot' => $listing->lot_snapshot,
            ],
        ];
    }

    protected function buildLotSelectionLineItem(
        Listing $listing,
        array $metadata,
        ?int $cartItemId = null
    ): array {
        $selection = $this->lotCardSelections->buildSelectionSnapshot(
            $listing,
            $metadata['selected_lot_card_ids'] ?? $metadata['lot_card_ids'] ?? []
        );

        return [
            'cart_item_id' => $cartItemId,
            'item_type' => 'listing',
            'seller_id' => (int) $listing->seller_id,
            'listing' => $listing,
            'draw_campaign' => null,
            'product' => $listing->product,
            'quantity' => 1,
            'unit_price' => (float) ($selection['selected_lot_cards_total'] ?? 0),
            'title' => $listing->title_snapshot ?: $listing->product?->title ?: __('api.orders.untitled_item'),
            'condition_snapshot' => $listing->condition,
            'metadata' => array_merge($selection, [
                'item_type' => 'listing',
                'sale_format' => $listing->sale_format,
                'rarity' => $listing->rarity,
                'lot_snapshot' => $listing->lot_snapshot,
            ]),
        ];
    }

    protected function buildDrawLineItem(
        DrawCampaign $drawCampaign,
        int $quantity,
        User $buyer,
        ?int $cartItemId = null
    ): array {
        if ($drawCampaign->campaign_type !== 'community_raffle' || ! $drawCampaign->host_user_id) {
            throw ValidationException::withMessages([
                'items' => [__('api.orders.invalid_items')],
            ]);
        }

        $this->drawEntries->assertCanPurchase($buyer, $drawCampaign, $quantity);

        return [
            'cart_item_id' => $cartItemId,
            'item_type' => 'draw_entry',
            'seller_id' => (int) $drawCampaign->host_user_id,
            'listing' => null,
            'draw_campaign' => $drawCampaign,
            'product' => null,
            'quantity' => $quantity,
            'unit_price' => round((float) ($drawCampaign->entry_price ?? 0), 2),
            'title' => $drawCampaign->prize_title ?: $drawCampaign->title ?: __('api.orders.untitled_item'),
            'condition_snapshot' => $drawCampaign->prize_condition,
            'metadata' => [
                'item_type' => 'draw_entry',
                'campaign_type' => $drawCampaign->campaign_type,
                'dispatch_window' => $drawCampaign->dispatch_window,
                'shipping_covered' => (bool) $drawCampaign->shipping_covered,
                'host_user_id' => (int) $drawCampaign->host_user_id,
            ],
        ];
    }

    protected function calculateShippingTotal(Collection $lineItems, array $payload): float
    {
        if (array_key_exists('shipping_total', $payload)) {
            return round((float) $payload['shipping_total'], 2);
        }

        $listingItems = $lineItems
            ->filter(fn (array $item) => $item['item_type'] === 'listing')
            ->values();

        if ($listingItems->isEmpty()) {
            return 0.0;
        }

        $shippingAddress = $payload['shipping_address'] ?? null;
        $isDomestic = $this->isDomesticGreekAddress($shippingAddress);

        if ($isDomestic) {
            return round(
                $listingItems->sum(function (array $item) {
                    if (($item['metadata']['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE) {
                        return (float) ($item['metadata']['domestic_shipping_total'] ?? 0);
                    }

                    return $this->resolveDomesticShippingCost($item['listing']) * $item['quantity'];
                }),
                2
            );
        }

        return round(
            $listingItems->sum(fn (array $item) =>
                $this->resolveInternationalShippingCost($item['listing'], $shippingAddress)
                * (
                    ($item['metadata']['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE
                        ? 1
                        : $item['quantity']
                )
            ),
            2
        );
    }

    protected function resolveDomesticShippingCost(Listing $listing): float
    {
        $shippingProfile = (string) ($listing->shipping_profile ?? '');

        if (in_array($shippingProfile, ['boxnow_domestic_only', 'boxnow_domestic_dhl_international', 'calculated_domestic_only', 'calculated_domestic_dhl_international'], true)) {
            return 0.0;
        }

        if (in_array($shippingProfile, ['dhl_domestic_only', 'dhl_domestic_dhl_international'], true)) {
            $configuredRate = data_get($listing->attributes, 'shipping.domestic.fee');

            if (is_numeric($configuredRate) && (float) $configuredRate >= 0) {
                return round((float) $configuredRate, 2);
            }
        }

        return max(0.0, (float) ($listing->shipping_cost ?? 0));
    }

    protected function resolveInternationalShippingCost(Listing $listing, mixed $shippingAddress): float
    {
        $shippingProfile = (string) ($listing->shipping_profile ?? '');
        $countryCode = $this->extractCountryCode($shippingAddress);
        $isCyprusAddress = $countryCode === 'CY' || $this->isCyprusAddress($shippingAddress);
        $cyprusParcelRate = $isCyprusAddress
            ? $this->extractCyprusParcelRate($listing)
            : 0.0;

        if ($isCyprusAddress && $cyprusParcelRate > 0) {
            return round($cyprusParcelRate, 2);
        }

        if (in_array($shippingProfile, ['boxnow_domestic_only', 'calculated_domestic_only', 'dhl_domestic_only'], true)) {
            throw ValidationException::withMessages([
                'shipping_address' => [__('api.orders.international_not_supported', [
                    'title' => $listing->title_snapshot ?: $listing->product?->title ?: __('api.orders.untitled_item'),
                ])],
            ]);
        }

        if (in_array($shippingProfile, ['boxnow_domestic_dhl_international', 'calculated_domestic_dhl_international', 'dhl_domestic_dhl_international'], true)) {
            $zone = $this->resolveInternationalZone($shippingAddress);
            $rates = $this->extractInternationalRates($listing);
            $rate = (float) ($rates[$zone] ?? $rates['restOfWorld'] ?? 0);

            if ($rate <= 0) {
                throw ValidationException::withMessages([
                    'shipping_address' => [__('api.orders.international_rate_missing', [
                        'title' => $listing->title_snapshot ?: $listing->product?->title ?: __('api.orders.untitled_item'),
                    ])],
                ]);
            }

            return round($rate, 2);
        }

        return max(0.0, (float) ($listing->shipping_cost ?? 0));
    }

    protected function resolveShippingCarrier(Collection $lineItems): ?string
    {
        $listing = $lineItems->pluck('listing')->filter()->first();

        if (! $listing instanceof Listing) {
            return null;
        }

        $shippingProfile = (string) ($listing->shipping_profile ?? '');
        $shippingMethods = collect($listing->shipping_methods ?? [])
            ->map(fn ($method) => Str::lower((string) $method));

        if (Str::startsWith($shippingProfile, 'dhl_') || $shippingMethods->contains('dhl express')) {
            return 'dhl_express';
        }

        if (Str::startsWith($shippingProfile, 'boxnow_') || $shippingMethods->contains('boxnow')) {
            if (! config('services.boxnow.enabled', false)) {
                throw ValidationException::withMessages([
                    'shipping_address' => [__('api.orders.carrier_unavailable', ['carrier' => 'BoxNow'])],
                ]);
            }

            return 'boxnow';
        }

        return null;
    }

    protected function extractCyprusParcelRate(Listing $listing): float
    {
        $listingRate = data_get($listing->attributes, 'shipping.domestic.rates.cy');
        if (is_numeric($listingRate) && (float) $listingRate > 0) {
            return max(0.0, (float) $listingRate);
        }

        $listingRateUppercase = data_get($listing->attributes, 'shipping.domestic.rates.CY');
        if (is_numeric($listingRateUppercase) && (float) $listingRateUppercase > 0) {
            return max(0.0, (float) $listingRateUppercase);
        }

        $legacyListingRate = data_get($listing->attributes, 'shipping.domestic.cyprus_fee');
        if (is_numeric($legacyListingRate) && (float) $legacyListingRate > 0) {
            return max(0.0, (float) $legacyListingRate);
        }

        $productRate = data_get($listing->product?->metadata, 'shipping.domestic.rates.cy');
        if (is_numeric($productRate) && (float) $productRate > 0) {
            return max(0.0, (float) $productRate);
        }

        $productRateUppercase = data_get($listing->product?->metadata, 'shipping.domestic.rates.CY');
        if (is_numeric($productRateUppercase) && (float) $productRateUppercase > 0) {
            return max(0.0, (float) $productRateUppercase);
        }

        $legacyProductRate = data_get($listing->product?->metadata, 'shipping.domestic.cyprus_fee');
        if (is_numeric($legacyProductRate) && (float) $legacyProductRate > 0) {
            return max(0.0, (float) $legacyProductRate);
        }

        return 0.0;
    }

    protected function isCyprusAddress(mixed $shippingAddress): bool
    {
        $countryCode = $this->extractCountryCode($shippingAddress);
        if ($countryCode !== '') {
            return $countryCode === 'CY';
        }

        if (is_string($shippingAddress) && $shippingAddress !== '') {
            $normalizedAddress = Str::of($shippingAddress)->lower()->ascii()->toString();
            if (Str::contains($normalizedAddress, ['cyprus', 'kypros', 'kipros'])) {
                return true;
            }
        }

        $countryName = $this->extractCountryName($shippingAddress);
        if ($countryName === '') {
            return false;
        }

        $normalizedCountry = Str::of($countryName)->lower()->ascii()->toString();

        return in_array($normalizedCountry, ['cy', 'cyprus', 'kypros', 'kipros'], true)
            || in_array(Str::of($countryName)->lower()->toString(), ['κύπρος', 'κυπρος'], true);
    }

    protected function extractInternationalRates(Listing $listing): array
    {
        $listingRates = data_get($listing->attributes, 'shipping.international.rates');

        if (is_array($listingRates) && $listingRates !== []) {
            return $this->normalizeRates($listingRates);
        }

        $productRates = data_get($listing->product?->metadata, 'shipping.international.rates');

        if (is_array($productRates) && $productRates !== []) {
            return $this->normalizeRates($productRates);
        }

        return [];
    }

    protected function normalizeRates(array $rates): array
    {
        return collect($rates)
            ->mapWithKeys(fn (mixed $value, mixed $key) => [(string) $key => max(0.0, (float) $value)])
            ->all();
    }

    protected function isDomesticGreekAddress(mixed $shippingAddress): bool
    {
        $countryCode = $this->extractCountryCode($shippingAddress);

        if ($countryCode !== '') {
            return $countryCode === 'GR';
        }

        if (is_string($shippingAddress) && $shippingAddress !== '') {
            $normalizedAddress = Str::of($shippingAddress)->lower()->ascii()->toString();
            $foreignMarkers = [
                'usa',
                'united states',
                'canada',
                'china',
                'uk',
                'united kingdom',
                'england',
                'germany',
                'france',
                'italy',
                'spain',
            ];

            if (Str::contains($normalizedAddress, $foreignMarkers)) {
                return false;
            }
        }

        $countryName = $this->extractCountryName($shippingAddress);

        if ($countryName === '') {
            return true;
        }

        $normalizedCountry = Str::of($countryName)->lower()->ascii()->toString();

        return in_array($normalizedCountry, ['gr', 'greece', 'ellada', 'ellas'], true)
            || in_array(Str::of($countryName)->lower()->toString(), ['ελλάδα', 'ελλαδα', 'ελλάς', 'ελλας'], true);
    }

    protected function resolveInternationalZone(mixed $shippingAddress): string
    {
        $countryCode = $this->extractCountryCode($shippingAddress);

        if ($countryCode === 'US') {
            return 'usa';
        }

        if ($countryCode === 'CA') {
            return 'canada';
        }

        if ($countryCode === 'CN') {
            return 'china';
        }

        if (in_array($countryCode, ['GB', 'UK'], true)) {
            return 'uk';
        }

        if ($countryCode !== '' && $this->isEuropeanCountryCode($countryCode)) {
            return 'europe';
        }

        return 'restOfWorld';
    }

    protected function extractCountryCode(mixed $shippingAddress): string
    {
        if (is_array($shippingAddress)) {
            $rawCode = $shippingAddress['country_code']
                ?? $shippingAddress['countryCode']
                ?? null;

            if (is_string($rawCode) && trim($rawCode) !== '') {
                return Str::upper(trim($rawCode));
            }

            $country = $shippingAddress['country'] ?? null;

            if (is_string($country) && strlen(trim($country)) === 2) {
                return Str::upper(trim($country));
            }

            return '';
        }

        if (is_string($shippingAddress) && strlen(trim($shippingAddress)) === 2) {
            return Str::upper(trim($shippingAddress));
        }

        return '';
    }

    protected function extractCountryName(mixed $shippingAddress): string
    {
        if (is_array($shippingAddress)) {
            $country = $shippingAddress['country'] ?? '';

            return is_string($country) ? trim($country) : '';
        }

        return '';
    }

    protected function isEuropeanCountryCode(string $countryCode): bool
    {
        return in_array($countryCode, [
            'AL', 'AD', 'AT', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO',
            'FR', 'GI', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MC', 'MD', 'ME', 'MK', 'MT',
            'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'SE', 'SI', 'SK', 'SM', 'UA', 'VA',
        ], true);
    }

    protected function restoreReservedInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->listing) {
                continue;
            }

            if (($item->metadata['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE) {
                $this->lotCardSelections->releaseReservation($item);
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
    }

    protected function calculateCommissionAmount(float $subtotal): float
    {
        return MarketplaceSellerFeeCalculator::calculate($subtotal);
    }

    protected function calculateBuyerFeeAmount(float $subtotal): float
    {
        $rate = (float) config('services.stripe.marketplace_buyer_fee_rate', 0.00);

        return round(max($subtotal, 0) * $rate, 2);
    }

    protected function confirmationWindowDays(): int
    {
        return max(1, (int) config('services.stripe.confirmation_window_days', 7));
    }

    protected function orderMailContent(?string $locale, string $variant, Order $order): array
    {
        $isEnglish = $locale === 'en';
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $buyerName = $order->buyer?->display_name ?: $order->buyer?->name ?: 'Buyer';
        $total = $this->formatOrderAmount($order);

        if ($isEnglish) {
            if ($variant === 'seller_paid') {
                return [
                    'subject' => sprintf('Order %s is paid on Cardora', $order->order_number),
                    'eyebrow' => 'Cardora orders',
                    'title' => 'A paid order is waiting for shipment',
                    'body' => sprintf('Order %s was paid successfully. Prepare shipping and keep tracking inside Cardora.', $order->order_number),
                    'details' => [
                        ['label' => 'Order', 'value' => $order->order_number],
                        ['label' => 'Buyer', 'value' => $buyerName],
                        ['label' => 'Total', 'value' => $total],
                    ],
                    'cta' => 'Open orders',
                    'url' => $frontendUrl.'/paraggelies',
                    'footer' => 'Seller funds remain protected until the release conditions are met.',
                ];
            }

            return [
                'subject' => sprintf('Your order %s is confirmed on Cardora', $order->order_number),
                'eyebrow' => 'Cardora orders',
                'title' => 'Your order is paid and protected',
                'body' => sprintf('Order %s was paid successfully and is now waiting for shipment.', $order->order_number),
                'details' => [
                    ['label' => 'Order', 'value' => $order->order_number],
                    ['label' => 'Total', 'value' => $total],
                ],
                'cta' => 'Open orders',
                'url' => $frontendUrl.'/paraggelies',
                'footer' => 'Keep shipping updates and issue reporting inside Cardora for full protection.',
            ];
        }

        if ($variant === 'seller_paid') {
            return [
                'subject' => sprintf('Η παραγγελία %s πληρώθηκε στην Cardora', $order->order_number),
                'eyebrow' => 'Παραγγελίες Cardora',
                'title' => 'Νέα πληρωμένη παραγγελία περιμένει αποστολή',
                'body' => sprintf('Η παραγγελία %s πληρώθηκε επιτυχώς. Ετοίμασε την αποστολή και κράτησε το tracking μέσα στην Cardora.', $order->order_number),
                'details' => [
                    ['label' => 'Παραγγελία', 'value' => $order->order_number],
                    ['label' => 'Αγοραστής', 'value' => $buyerName],
                    ['label' => 'Σύνολο', 'value' => $total],
                ],
                'cta' => 'Άνοιγμα παραγγελιών',
                'url' => $frontendUrl.'/paraggelies',
                'footer' => 'Το ποσό του πωλητή παραμένει προστατευμένο μέχρι να καλυφθούν οι όροι αποδέσμευσης.',
            ];
        }

        return [
            'subject' => sprintf('Η παραγγελία σου %s επιβεβαιώθηκε στην Cardora', $order->order_number),
            'eyebrow' => 'Παραγγελίες Cardora',
            'title' => 'Η παραγγελία σου πληρώθηκε και προστατεύεται',
            'body' => sprintf('Η παραγγελία %s πληρώθηκε επιτυχώς και τώρα περιμένει αποστολή.', $order->order_number),
            'details' => [
                ['label' => 'Παραγγελία', 'value' => $order->order_number],
                ['label' => 'Σύνολο', 'value' => $total],
            ],
            'cta' => 'Άνοιγμα παραγγελιών',
            'url' => $frontendUrl.'/paraggelies',
            'footer' => 'Κράτησε τις ενημερώσεις αποστολής και οποιοδήποτε θέμα μέσα στην Cardora για πλήρη προστασία.',
        ];
    }

    protected function formatOrderAmount(Order $order): string
    {
        return number_format((float) ($order->total_amount ?: $order->total), 2, ',', '.').' '.strtoupper((string) $order->currency);
    }

    protected function mergeMetadata(?array $base, ?array $extra): array
    {
        return array_filter(
            array_merge($base ?? [], $extra ?? []),
            fn ($value) => $value !== null
        );
    }

    protected function generateOrderNumber(): string
    {
        return 'CDR-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
    }

    protected function orderRelations(): array
    {
        return [
            'buyer',
            'seller',
            'product',
            'items.listing.product.category',
            'items.product.category',
            'items.drawCampaign.hostUser',
            'escrowTransaction',
        ];
    }
}
