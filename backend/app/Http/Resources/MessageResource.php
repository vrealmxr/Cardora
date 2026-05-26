<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'body_masked' => $this->body_masked,
            'display_body' => $this->body_masked ?: $this->body,
            'attachments' => $this->attachments,
            'offer_amount' => $this->offer_amount,
            'metadata' => $this->metadata,
            'moderation_status' => $this->moderation_status,
            'moderation_flags' => $this->moderation_flags ?? [],
            'moderation_score' => $this->moderation_score,
            'requires_admin_review' => (bool) $this->requires_admin_review,
            'reviewed_at' => $this->reviewed_at,
            'reviewed_by' => $this->reviewed_by,
            'read_at' => $this->read_at,
            'sender' => new UserProfileResource($this->whenLoaded('sender')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
