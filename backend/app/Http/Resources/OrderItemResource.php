<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'draw_campaign_id' => $this->draw_campaign_id,
            'product_id' => $this->product_id,
            'title_snapshot' => $this->title_snapshot,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'condition_snapshot' => $this->condition_snapshot,
            'metadata' => $this->metadata,
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'draw_campaign' => new DrawCampaignResource($this->whenLoaded('drawCampaign')),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
