<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'seller_id' => $this->seller_id,
            'title_snapshot' => $this->title_snapshot,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'minimum_offer' => $this->minimum_offer,
            'quantity' => $this->quantity,
            'available_quantity' => $this->available_quantity,
            'condition' => $this->condition,
            'rarity' => $this->rarity,
            'status' => $this->status,
            'sale_format' => $this->sale_format,
            'shipping_cost' => $this->shipping_cost,
            'shipping_profile' => $this->shipping_profile,
            'shipping_methods' => $this->shipping_methods,
            'dispatch_time' => $this->dispatch_time,
            'packaging_notes' => $this->packaging_notes,
            'availability' => $this->availability,
            'accept_offers' => $this->accept_offers,
            'is_featured' => $this->is_featured,
            'featured_until' => $this->featured_until,
            'featured_payment_id' => $this->featured_payment_id,
            'auction_settings' => $this->auction_settings,
            'starting_bid' => $this->starting_bid,
            'current_bid' => $this->current_bid,
            'reserve_price' => $this->reserve_price,
            'bid_increment' => $this->bid_increment,
            'buyout_price' => $this->buyout_price,
            'auction_starts_at' => $this->auction_starts_at,
            'auction_ends_at' => $this->auction_ends_at,
            'lot_snapshot' => $this->lot_snapshot,
            'auction_bids_count' => $this->whenCounted('bids'),
            'published_at' => $this->published_at,
            'expires_at' => $this->expires_at,
            'attributes' => $this->attributes,
            'compliance_flags' => $this->compliance_flags,
            'product' => new ProductResource($this->whenLoaded('product')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'seller' => new UserProfileResource($this->whenLoaded('seller')),
            'winning_bidder' => new UserProfileResource($this->whenLoaded('winningBidder')),
        ];
    }
}
