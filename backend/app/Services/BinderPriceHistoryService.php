<?php

namespace App\Services;

use App\Models\BinderCardPricePoint;
use App\Models\Listing;
use App\Models\Order;

/**
 * Real, first-party price history for Binder cards — sourced entirely from
 * Cardora's own marketplace activity (a listing going live, a sale actually
 * completing). No external market-data API involved.
 */
class BinderPriceHistoryService
{
    public function __construct(
        protected BinderPriceAlertService $priceAlerts
    ) {
    }

    /**
     * A listing tied to a Binder card just went active/published — record
     * one "asking price" data point. Deduped per listing so repeated status
     * churn on the same listing doesn't create duplicate points.
     */
    public function recordListingPricePoint(Listing $listing): void
    {
        $listing->loadMissing('product');
        $binderCardId = $listing->product?->binder_card_id;

        if (! $binderCardId) {
            return;
        }

        $alreadyRecorded = BinderCardPricePoint::query()
            ->where('listing_id', $listing->getKey())
            ->where('source', 'listing_created')
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $point = BinderCardPricePoint::create([
            'binder_card_id' => $binderCardId,
            'listing_id' => $listing->getKey(),
            'price' => $listing->price,
            'currency' => 'EUR',
            'source' => 'listing_created',
            'recorded_at' => now(),
        ]);

        $this->priceAlerts->checkForAlert($point);
    }

    /**
     * An order for one or more Binder-matched cards was just released to
     * the seller (i.e. a real sale completed) — record one "sold" data
     * point per matched line item. This is the strongest price signal we
     * have, since it's an actual completed transaction, not just an ask.
     */
    public function recordOrderSalePricePoints(Order $order): void
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $binderCardId = $item->product?->binder_card_id;
            if (! $binderCardId) {
                continue;
            }

            $point = BinderCardPricePoint::create([
                'binder_card_id' => $binderCardId,
                'listing_id' => $item->listing_id,
                'price' => $item->unit_price,
                'currency' => 'EUR',
                'source' => 'listing_sold',
                'recorded_at' => now(),
            ]);

            $this->priceAlerts->checkForAlert($point);
        }
    }
}
