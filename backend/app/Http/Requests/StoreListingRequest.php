<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'required_without:product', 'exists:products,id'],
            'product' => ['nullable', 'required_without:product_id', 'array'],
            'product.title' => ['required_with:product', 'string', 'max:255'],
            'product.subtitle' => ['nullable', 'string', 'max:255'],
            'product.franchise' => ['nullable', 'string', 'max:255'],
            'product.series' => ['nullable', 'string', 'max:255'],
            'product.brand' => ['nullable', 'string', 'max:255'],
            'product.year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'product.language' => ['nullable', 'string', 'max:100'],
            'product.set_name' => ['nullable', 'string', 'max:255'],
            'product.item_number' => ['nullable', 'string', 'max:255'],
            'product.binder_card_id' => ['nullable', 'integer', 'exists:binder_cards,id'],
            'product.product_type' => ['nullable', 'string', 'max:255'],
            'product.description' => ['nullable', 'string'],
            'product.specifications' => ['nullable', 'array'],
            'product.tags' => ['nullable', 'array'],
            'product.media' => ['nullable', 'array'],
            'product.authenticity_notes' => ['nullable', 'string'],
            'product.is_authenticated' => ['nullable', 'boolean'],
            'product.is_lot' => ['nullable', 'boolean'],
            'product.lot_configuration' => ['nullable', 'array'],
            'product.metadata' => ['nullable', 'array'],
            'category_id' => ['required', 'exists:categories,id'],
            'seller_id' => ['nullable', 'exists:users,id'],
            'title_snapshot' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_offer' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'available_quantity' => ['nullable', 'integer', 'min:0'],
            'condition' => ['nullable', 'string', 'max:255'],
            'rarity' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'sale_format' => ['nullable', Rule::in(['fixed_price', 'auction', 'trade'])],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'shipping_profile' => ['nullable', 'string', 'max:255'],
            'shipping_methods' => ['nullable', 'array'],
            'dispatch_time' => ['nullable', 'string', 'max:100'],
            'packaging_notes' => ['nullable', 'string'],
            'availability' => ['nullable', 'string', 'max:100'],
            'accept_offers' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'featured_payment_id' => ['nullable', 'integer', 'exists:featured_listing_payments,id'],
            'auction_settings' => ['nullable', 'array'],
            'starting_bid' => ['nullable', 'numeric', 'min:0'],
            'current_bid' => ['nullable', 'numeric', 'min:0'],
            'reserve_price' => ['nullable', 'numeric', 'min:0'],
            'bid_increment' => ['nullable', 'numeric', 'min:0'],
            'buyout_price' => ['nullable', 'numeric', 'min:0'],
            'auction_starts_at' => ['nullable', 'date'],
            'auction_ends_at' => ['nullable', 'date'],
            'winning_bidder_id' => ['nullable', 'exists:users,id'],
            'lot_snapshot' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'attributes' => ['nullable', 'array'],
            'compliance_flags' => ['nullable', 'array'],
            'moderation_notes' => ['nullable', 'string'],
        ];
    }
}
