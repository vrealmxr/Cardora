<?php

namespace App\Http\Resources;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = (array) ($this->offered_metadata ?? []);
        $offeredListingIds = collect((array) ($this->offered_listing_ids ?? []))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($offeredListingIds === []) {
            $offeredListingIds = collect((array) Arr::get($metadata, 'offered_listing_ids', []))
                ->map(fn ($item) => (int) $item)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        if ($offeredListingIds === []) {
            $offeredListingIds = collect((array) Arr::get($metadata, 'proposer_bundle', []))
                ->map(fn ($item) => (int) Arr::get((array) $item, 'listing_id', 0))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $targetListingIds = collect((array) ($this->target_listing_ids ?? []))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($targetListingIds === []) {
            $targetListingIds = collect((array) Arr::get($metadata, 'target_listing_ids', []))
                ->map(fn ($item) => (int) $item)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        if ($targetListingIds === []) {
            $targetListingIds = collect((array) Arr::get($metadata, 'owner_bundle', []))
                ->map(fn ($item) => (int) Arr::get((array) $item, 'listing_id', 0))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $swapPairs = $this->swap_pairs;
        if (! is_array($swapPairs) || $swapPairs === []) {
            $swapPairs = (array) Arr::get($metadata, 'swap_pairs', []);
        }

        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'requester_user_id' => $this->requester_user_id,
            'listing_owner_user_id' => $this->listing_owner_user_id,
            'status' => $this->status,
            'offered_title' => $this->offered_title,
            'offered_description' => $this->offered_description,
            'offered_condition' => $this->offered_condition,
            'offered_value' => $this->offered_value,
            'offered_images' => $this->offered_images,
            'offered_listing_ids' => $offeredListingIds,
            'target_listing_ids' => $targetListingIds,
            'swap_pairs' => $swapPairs,
            'offered_metadata' => $this->offered_metadata,
            'request_message' => $this->request_message,
            'terms_accepted' => $this->terms_accepted,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'cancelled_at' => $this->cancelled_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'requester' => new UserProfileResource($this->whenLoaded('requester')),
            'listing_owner' => new UserProfileResource($this->whenLoaded('listingOwner')),
            'trade_deal' => new TradeDealResource($this->whenLoaded('tradeDeal')),
        ];
    }
}
