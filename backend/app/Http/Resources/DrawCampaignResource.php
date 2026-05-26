<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrawCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'host_user_id' => $this->host_user_id,
            'winner_user_id' => $this->winner_user_id,
            'prize_listing_id' => $this->prize_listing_id,
            'campaign_type' => $this->campaign_type,
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'prize_title' => $this->prize_title,
            'prize_category' => $this->prize_category,
            'prize_condition' => $this->prize_condition,
            'prize_value' => $this->prize_value,
            'entry_price' => $this->entry_price,
            'entries_per_euro' => $this->entries_per_euro,
            'target_amount' => $this->target_amount,
            'current_amount' => $this->current_amount,
            'target_entries' => $this->target_entries,
            'entries_issued' => $this->entries_issued,
            'sold_entries' => $this->sold_entries,
            'participants_count' => $this->participants_count,
            'max_entries_per_user' => $this->max_entries_per_user,
            'status' => $this->status,
            'featured' => $this->featured,
            'requires_verification' => $this->requires_verification,
            'shipping_covered' => $this->shipping_covered,
            'fairness_note' => $this->fairness_note,
            'dispatch_window' => $this->dispatch_window,
            'rules' => $this->rules,
            'eligibility' => $this->eligibility,
            'visual' => $this->visual,
            'draw_result' => $this->draw_result,
            'locked_at' => $this->locked_at,
            'ends_at' => $this->ends_at,
            'draw_at' => $this->draw_at,
            'drawn_at' => $this->drawn_at,
            'host_user' => new UserProfileResource($this->whenLoaded('hostUser')),
            'winner' => new UserProfileResource($this->whenLoaded('winner')),
            'prize_listing' => new ListingResource($this->whenLoaded('prizeListing')),
            'entries' => DrawEntryResource::collection($this->whenLoaded('entries')),
            'entries_count' => $this->whenCounted('entries'),
        ];
    }
}
