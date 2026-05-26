<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CartSanitizerService
{
    public function __construct(
        protected DrawEntryService $drawEntries,
        protected LotCardSelectionService $lotCardSelections
    ) {
    }

    public function purgeInvalidItems(User $user): int
    {
        $invalidIds = $user->cartItems()
            ->with(['listing', 'drawCampaign'])
            ->get()
            ->filter(fn (CartItem $item) => ! $this->isValidCartItem($user, $item))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($invalidIds->isEmpty()) {
            return 0;
        }

        CartItem::query()
            ->where('user_id', $user->getKey())
            ->whereIn('id', $invalidIds->all())
            ->delete();

        return $invalidIds->count();
    }

    protected function isValidCartItem(User $user, CartItem $item): bool
    {
        if ($item->draw_campaign_id) {
            return $this->isValidDrawItem($user, $item);
        }

        return $this->isValidListingItem($user, $item);
    }

    protected function isValidListingItem(User $user, CartItem $item): bool
    {
        $listing = $item->listing;

        if (! $listing) {
            return false;
        }

        if ((int) $listing->seller_id === (int) $user->getKey()) {
            return false;
        }

        if (($listing->sale_format ?? 'fixed_price') === 'auction') {
            return false;
        }

        if (($item->metadata['item_mode'] ?? null) === LotCardSelectionService::ITEM_MODE) {
            return $this->lotCardSelections->hasValidSelection($listing, $item->metadata ?? []);
        }

        try {
            $this->lotCardSelections->assertWholeLotPurchaseAllowed($listing);
        } catch (ValidationException) {
            return false;
        }

        if (! in_array((string) $listing->status, ['active', 'published'], true)) {
            return false;
        }

        if ((string) $listing->availability === 'cancelled') {
            return false;
        }

        $availableQuantity = (int) ($listing->available_quantity ?? $listing->quantity ?? 0);
        $requestedQuantity = (int) $item->quantity;

        return $requestedQuantity >= 1
            && $availableQuantity >= 1
            && $requestedQuantity <= $availableQuantity;
    }

    protected function isValidDrawItem(User $user, CartItem $item): bool
    {
        if (! $item->drawCampaign) {
            return false;
        }

        try {
            $this->drawEntries->assertCanPurchase($user, $item->drawCampaign, (int) $item->quantity);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}

