<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'listing_id' => $this->listing_id,
            'draw_campaign_id' => $this->draw_campaign_id,
            'quantity' => $this->quantity,
            'item_type' => $this->draw_campaign_id ? 'draw_entry' : 'listing',
            'metadata' => $this->metadata,
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'draw_campaign' => new DrawCampaignResource($this->whenLoaded('drawCampaign')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
