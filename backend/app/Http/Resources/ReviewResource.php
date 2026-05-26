<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'listing_id' => $this->listing_id,
            'product_id' => $this->product_id,
            'reviewer_id' => $this->reviewer_id,
            'reviewee_id' => $this->reviewee_id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'is_public' => $this->is_public,
            'reviewer' => new UserProfileResource($this->whenLoaded('reviewer')),
            'reviewee' => new UserProfileResource($this->whenLoaded('reviewee')),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
