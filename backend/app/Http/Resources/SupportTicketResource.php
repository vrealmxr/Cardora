<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
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
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'subject' => $this->subject,
            'category' => $this->category,
            'status' => $this->status,
            'priority' => $this->priority,
            'description' => $this->description,
            'attachments' => $this->attachments,
            'metadata' => $this->metadata,
            'resolution' => $this->resolution,
            'resolved_at' => $this->resolved_at,
            'user' => new UserProfileResource($this->whenLoaded('user')),
            'order' => new OrderResource($this->whenLoaded('order')),
            'created_at' => $this->created_at,
        ];
    }
}
