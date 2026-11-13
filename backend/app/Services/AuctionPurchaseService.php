<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuctionPurchaseService
{
    public function assertOpenForBid(Listing $listing, User $bidder): void
    {
        $this->assertAvailable($listing, $bidder);
        if (! $listing->auction_ends_at || $listing->auction_ends_at->lte(now())
            || ($listing->auction_starts_at && $listing->auction_starts_at->gt(now()))) {
            $this->deny('This auction is not open for bids.', 'Η δημοπρασία δεν είναι ανοιχτή για προσφορές.');
        }
    }

    public function assertWinnerCanPurchase(Listing $listing, User $buyer, int $quantity = 1): void
    {
        $this->assertAvailable($listing, $buyer);
        if ($quantity !== 1 || ! $listing->auction_ends_at || $listing->auction_ends_at->gt(now())
            || (int) $listing->winning_bidder_id !== (int) $buyer->getKey()
            || (float) $listing->current_bid <= 0
            || (float) $listing->current_bid < (float) ($listing->reserve_price ?? 0)
            || ! $listing->bids()->where('bidder_id', $buyer->getKey())
                ->where('amount', $listing->current_bid)->where('status', 'submitted')->exists()) {
            $this->deny('Only the winning bidder can purchase after the auction ends and the reserve is met.',
                'Η αγορά γίνεται μόνο από τον νικητή, μετά τη λήξη και εφόσον καλύφθηκε η ελάχιστη τιμή πώλησης.');
        }
    }

    protected function assertAvailable(Listing $listing, User $buyer): void
    {
        if ($listing->sale_format !== 'auction' || (int) $listing->seller_id === (int) $buyer->getKey()
            || ! in_array((string) $listing->status, ['active', 'published'], true)
            || in_array((string) $listing->availability, ['cancelled', 'sold_out'], true)
            || (int) ($listing->available_quantity ?? $listing->quantity ?? 0) < 1) {
            $this->deny('This auction is unavailable for this action.', 'Η δημοπρασία δεν είναι διαθέσιμη για αυτή την ενέργεια.');
        }
        $listing->loadMissing('seller');
        if (! $listing->seller) {
            $this->deny('Seller unavailable.', 'Ο πωλητής δεν είναι διαθέσιμος.');
        }
        // Relaxing buyer access never relaxes the seller requirements.
        app(MarketplaceAccessService::class)->assertCanSell($listing->seller, app()->getLocale());
    }

    protected function deny(string $en, string $el): never
    {
        throw ValidationException::withMessages(['auction' => [app()->getLocale() === 'en' ? $en : $el]]);
    }
}
