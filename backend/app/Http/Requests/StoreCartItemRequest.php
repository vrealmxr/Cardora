<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['nullable', 'required_without:draw_campaign_id', 'prohibits:draw_campaign_id', 'exists:listings,id'],
            'draw_campaign_id' => ['nullable', 'required_without:listing_id', 'prohibits:listing_id', 'exists:draw_campaigns,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'metadata.item_mode' => ['nullable', 'string'],
            'metadata.lot_card_ids' => ['nullable', 'array', 'min:1'],
            'metadata.lot_card_ids.*' => ['string'],
        ];
    }
}
