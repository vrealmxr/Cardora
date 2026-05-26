<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionBidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'bidder_id' => $this->bidder_id,
            'amount' => $this->amount,
            'placed_at' => $this->placed_at,
            'status' => $this->status,
            'ip_address' => $this->ip_address,
            'metadata' => $this->metadata,
            'bidder' => new UserProfileResource($this->whenLoaded('bidder')),
        ];
    }
}
