<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\DrawCampaign;
use App\Models\Listing;
use App\Services\CartSanitizerService;
use App\Services\DrawEntryService;
use App\Services\LotCardSelectionService;
use App\Services\MarketplaceAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request, CartSanitizerService $cartSanitizer)
    {
        $cartSanitizer->purgeInvalidItems($request->user());

        $items = CartItem::query()
            ->with($this->cartItemRelations())
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->get();

        return CartItemResource::collection($items);
    }

    public function store(
        StoreCartItemRequest $request,
        DrawEntryService $drawEntries,
        MarketplaceAccessService $marketplaceAccess,
        LotCardSelectionService $lotCardSelections
    )
    {
        $marketplaceAccess->assertCanBuy($request->user(), app()->getLocale());

        if ($request->filled('draw_campaign_id')) {
            return $this->storeDrawItem($request, $drawEntries);
        }

        return $this->storeListingItem($request, $lotCardSelections);
    }

    public function show(Request $request, CartItem $cart)
    {
        $this->ensureOwnership($request, $cart);

        return new CartItemResource($cart->load($this->cartItemRelations()));
    }

    public function update(
        UpdateCartItemRequest $request,
        CartItem $cart,
        DrawEntryService $drawEntries
    ) {
        $this->ensureOwnership($request, $cart);

        $quantity = $request->integer('quantity');

        if ($cart->draw_campaign_id) {
            $drawCampaign = $cart->drawCampaign;

            if (! $drawCampaign) {
                throw ValidationException::withMessages([
                    'draw_campaign_id' => [__('api.orders.invalid_items')],
                ]);
            }

            $drawEntries->assertCanPurchase($request->user(), $drawCampaign, $quantity);
        } else {
            $listing = $cart->listing;

            if (! $listing) {
                throw ValidationException::withMessages([
                    'listing_id' => [__('api.orders.invalid_items')],
                ]);
            }

            if (($cart->metadata['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE) {
                throw ValidationException::withMessages([
                    'quantity' => [__('api.cart.lot_individual_quantity_locked')],
                ]);
            }

            app(LotCardSelectionService::class)->assertWholeLotPurchaseAllowed($listing);
            $this->ensureListingQuantityAvailable($listing, $quantity);
        }

        $cart->update(['quantity' => $quantity]);

        return response()->json([
            'message' => __('api.cart.updated'),
            'data' => new CartItemResource($cart->fresh()->load($this->cartItemRelations())),
        ]);
    }

    public function destroy(Request $request, CartItem $cart)
    {
        $this->ensureOwnership($request, $cart);

        $cart->delete();

        return response()->json([
            'message' => __('api.cart.removed'),
        ]);
    }

    protected function storeListingItem(StoreCartItemRequest $request, LotCardSelectionService $lotCardSelections)
    {
        $listing = Listing::query()->findOrFail($request->integer('listing_id'));

        if ((int) $listing->seller_id === (int) $request->user()->getKey()) {
            throw ValidationException::withMessages([
                'listing_id' => [__('api.cart.cannot_add_own_listing')],
            ]);
        }

        if ($listing->sale_format === 'auction') {
            throw ValidationException::withMessages([
                'listing_id' => [__('api.cart.auction_not_supported')],
            ]);
        }

        if ($listing->sale_format === 'trade') {
            throw ValidationException::withMessages([
                'listing_id' => ['Trade listings cannot be added to cart. Use the trade request flow instead.'],
            ]);
        }

        $quantity = $request->integer('quantity', 1);
        $metadata = $request->validated('metadata') ?? [];

        if (($metadata['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE) {
            $selection = $lotCardSelections->buildSelectionSnapshot(
                $listing,
                $metadata['lot_card_ids'] ?? []
            );

            $existingItem = CartItem::query()
                ->where('user_id', $request->user()->getKey())
                ->where('listing_id', $listing->getKey())
                ->whereNull('draw_campaign_id')
                ->where('metadata->item_mode', LotCardSelectionService::ITEM_MODE)
                ->where('metadata->selection_key', $selection['selection_key'])
                ->first();

            if ($existingItem) {
                return response()->json([
                    'message' => __('api.cart.added'),
                    'data' => new CartItemResource($existingItem->load($this->cartItemRelations())),
                ], 200);
            }

            $item = CartItem::create([
                'user_id' => $request->user()->getKey(),
                'listing_id' => $listing->getKey(),
                'draw_campaign_id' => null,
                'quantity' => 1,
                'metadata' => $selection,
            ]);

            return response()->json([
                'message' => __('api.cart.added'),
                'data' => new CartItemResource($item->load($this->cartItemRelations())),
            ], 201);
        }

        $lotCardSelections->assertWholeLotPurchaseAllowed($listing);
        $this->ensureListingQuantityAvailable($listing, $quantity);

        $item = CartItem::updateOrCreate(
            [
                'user_id' => $request->user()->getKey(),
                'listing_id' => $listing->getKey(),
                'draw_campaign_id' => null,
            ],
            [
                'quantity' => $quantity,
                'metadata' => null,
            ]
        );

        return response()->json([
            'message' => __('api.cart.added'),
            'data' => new CartItemResource($item->load($this->cartItemRelations())),
        ], 201);
    }

    protected function storeDrawItem(StoreCartItemRequest $request, DrawEntryService $drawEntries)
    {
        $drawCampaign = DrawCampaign::query()
            ->with('hostUser')
            ->findOrFail($request->integer('draw_campaign_id'));

        if ($drawCampaign->campaign_type !== 'community_raffle' || ! $drawCampaign->host_user_id) {
            throw ValidationException::withMessages([
                'draw_campaign_id' => [__('api.orders.invalid_items')],
            ]);
        }

        $quantity = $request->integer('quantity', 1);
        $drawEntries->assertCanPurchase($request->user(), $drawCampaign, $quantity);

        $item = CartItem::updateOrCreate(
            [
                'user_id' => $request->user()->getKey(),
                'draw_campaign_id' => $drawCampaign->getKey(),
            ],
            [
                'listing_id' => null,
                'quantity' => $quantity,
            ]
        );

        return response()->json([
            'message' => __('api.cart.added'),
            'data' => new CartItemResource($item->load($this->cartItemRelations())),
        ], 201);
    }

    protected function ensureListingQuantityAvailable(Listing $listing, int $quantity): void
    {
        $availableQuantity = (int) ($listing->available_quantity ?? $listing->quantity ?? 0);

        if ($quantity > $availableQuantity) {
            throw ValidationException::withMessages([
                'quantity' => [__('api.cart.insufficient_stock')],
            ]);
        }
    }

    protected function ensureOwnership(Request $request, CartItem $cart): void
    {
        abort_unless(
            $cart->user_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );
    }

    protected function cartItemRelations(): array
    {
        return [
            'listing.product.category',
            'listing.seller',
            'drawCampaign.hostUser',
        ];
    }
}
