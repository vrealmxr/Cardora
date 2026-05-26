<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrawEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'draw_campaign_id' => $this->draw_campaign_id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'entries' => $this->entries,
            'amount' => $this->amount,
            'source_type' => $this->source_type,
            'source_reference' => $this->source_reference,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'entered_at' => $this->entered_at,
            'user' => new UserProfileResource($this->whenLoaded('user')),
            'order' => new OrderResource($this->whenLoaded('order')),
        ];
    }
}
