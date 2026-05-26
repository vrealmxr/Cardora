<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                'pending_payment',
                'paid_pending_release',
                'released',
                'disputed',
                'refunded',
                'cancelled',
            ])],
            'escrow_status' => ['nullable', 'string', 'max:50'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.full_name' => ['nullable', 'string', 'max:255'],
            'shipping_address.address_line_1' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:100'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.country' => ['nullable', 'string', 'max:120'],
            'shipping_address.country_code' => ['nullable', 'string', 'size:2'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.full_name' => ['nullable', 'string', 'max:255'],
            'billing_address.address_line_1' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:100'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:30'],
            'billing_address.country' => ['nullable', 'string', 'max:120'],
            'billing_address.country_code' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string'],
            'placed_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.listing_id' => ['nullable', 'exists:listings,id'],
            'items.*.draw_campaign_id' => ['nullable', 'exists:draw_campaigns,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
