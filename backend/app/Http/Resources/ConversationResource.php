<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'listing_id' => $this->listing_id,
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'status' => $this->status,
            'last_message_at' => $this->last_message_at,
            'metadata' => $this->metadata,
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'buyer' => new UserProfileResource($this->whenLoaded('buyer')),
            'seller' => new UserProfileResource($this->whenLoaded('seller')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
