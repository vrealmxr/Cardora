<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerificationSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'verification_type' => $this->verification_type,
            'status' => $this->status,
            'payload' => $this->payload,
            'requirements_snapshot' => $this->requirements_snapshot,
            'reviewer_notes' => $this->reviewer_notes,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'user' => new UserProfileResource($this->whenLoaded('user')),
            'reviewer' => new UserProfileResource($this->whenLoaded('reviewer')),
            'documents' => VerificationDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
