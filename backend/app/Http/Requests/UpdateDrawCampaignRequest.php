<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDrawCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campaignId = $this->route('drawCampaign')?->getKey();

        return [
            'host_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'winner_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'prize_listing_id' => ['sometimes', 'nullable', 'exists:listings,id'],
            'campaign_type' => ['sometimes', Rule::in(['platform_volume', 'community_raffle'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('draw_campaigns', 'slug')->ignore($campaignId)],
            'subtitle' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'prize_title' => ['sometimes', 'string', 'max:255'],
            'prize_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'prize_condition' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prize_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'entry_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'entries_per_euro' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'target_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'current_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_entries' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entries_issued' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sold_entries' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'participants_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_entries_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['draft', 'review', 'active', 'locked', 'completed', 'cancelled'])],
            'featured' => ['sometimes', 'boolean'],
            'requires_verification' => ['sometimes', 'boolean'],
            'shipping_covered' => ['sometimes', 'boolean'],
            'fairness_note' => ['sometimes', 'nullable', 'string'],
            'dispatch_window' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rules' => ['sometimes', 'nullable', 'array'],
            'eligibility' => ['sometimes', 'nullable', 'array'],
            'visual' => ['sometimes', 'nullable', 'array'],
            'draw_result' => ['sometimes', 'nullable', 'array'],
            'locked_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'draw_at' => ['sometimes', 'nullable', 'date'],
            'drawn_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
