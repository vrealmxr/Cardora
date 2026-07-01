<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accepted_offer_id' => ['nullable', 'exists:listing_offers,id'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.full_name' => ['nullable', 'string', 'max:255'],
            'shipping_address.address_line_1' => ['nullable', 'string', 'max:255'],
            'shipping_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:100'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.country' => ['nullable', 'string', 'max:120'],
            'shipping_address.country_code' => ['nullable', 'string', 'size:2'],
            'shipping_address.phone' => ['nullable', 'string', 'max:50'],
            'shipping_address.delivery_type' => ['nullable', Rule::in(['home_delivery', 'service_point'])],
            'shipping_address.service_point' => ['nullable', 'array'],
            'shipping_address.service_point.id' => ['nullable', 'string', 'max:120'],
            'shipping_address.service_point.name' => ['nullable', 'string', 'max:255'],
            'shipping_address.service_point.address' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.full_name' => ['nullable', 'string', 'max:255'],
            'billing_address.address_line_1' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:100'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:30'],
            'billing_address.country' => ['nullable', 'string', 'max:120'],
            'billing_address.country_code' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.listing_id' => ['nullable', 'exists:listings,id'],
            'items.*.draw_campaign_id' => ['nullable', 'exists:draw_campaigns,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.metadata' => ['nullable', 'array'],
            'items.*.metadata.item_mode' => ['nullable', 'string', 'max:100'],
            'items.*.metadata.lot_card_ids' => ['nullable', 'array'],
            'items.*.metadata.lot_card_ids.*' => ['nullable', 'string', 'max:120'],
        ];
    }
}
