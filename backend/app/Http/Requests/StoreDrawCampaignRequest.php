<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDrawCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'winner_user_id' => ['nullable', 'exists:users,id'],
            'prize_listing_id' => ['nullable', 'exists:listings,id'],
            'campaign_type' => ['required', Rule::in(['platform_volume', 'community_raffle'])],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:draw_campaigns,slug'],
            'subtitle' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'prize_title' => ['required', 'string', 'max:255'],
            'prize_category' => ['nullable', 'string', 'max:100'],
            'prize_condition' => ['nullable', 'string', 'max:255'],
            'prize_value' => ['nullable', 'numeric', 'min:0'],
            'entry_price' => ['nullable', 'numeric', 'min:0'],
            'entries_per_euro' => ['nullable', 'integer', 'min:1'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'current_amount' => ['nullable', 'numeric', 'min:0'],
            'target_entries' => ['nullable', 'integer', 'min:1'],
            'entries_issued' => ['nullable', 'integer', 'min:0'],
            'sold_entries' => ['nullable', 'integer', 'min:0'],
            'participants_count' => ['nullable', 'integer', 'min:0'],
            'max_entries_per_user' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'review', 'active', 'locked', 'completed', 'cancelled'])],
            'featured' => ['nullable', 'boolean'],
            'requires_verification' => ['nullable', 'boolean'],
            'shipping_covered' => ['nullable', 'boolean'],
            'fairness_note' => ['nullable', 'string'],
            'dispatch_window' => ['nullable', 'string', 'max:255'],
            'rules' => ['nullable', 'array'],
            'eligibility' => ['nullable', 'array'],
            'visual' => ['nullable', 'array'],
            'draw_result' => ['nullable', 'array'],
            'locked_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'draw_at' => ['nullable', 'date'],
            'drawn_at' => ['nullable', 'date'],
        ];
    }
}
