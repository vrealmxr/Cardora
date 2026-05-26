<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTradeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'exists:listings,id'],
            'offered_title' => ['nullable', 'string', 'max:255'],
            'offered_description' => ['nullable', 'string'],
            'offered_condition' => ['nullable', 'string', 'max:255'],
            'offered_value' => ['nullable', 'numeric', 'min:0.01'],
            'offered_images' => ['nullable', 'array'],
            'offered_images.*' => ['nullable', 'string', 'max:1000'],
            'offered_listing_ids' => ['nullable', 'array', 'min:1'],
            'offered_listing_ids.*' => ['required', 'integer', 'distinct', 'exists:listings,id'],
            'target_listing_ids' => ['nullable', 'array', 'min:1'],
            'target_listing_ids.*' => ['required', 'integer', 'distinct', 'exists:listings,id'],
            'swap_pairs' => ['nullable', 'array'],
            'swap_pairs.*.from_listing_id' => ['required_with:swap_pairs', 'integer', 'exists:listings,id'],
            'swap_pairs.*.to_listing_id' => ['required_with:swap_pairs', 'integer', 'exists:listings,id'],
            'offered_metadata' => ['nullable', 'array'],
            'request_message' => ['nullable', 'string'],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $offeredListingIds = array_values(array_filter((array) $this->input('offered_listing_ids', [])));
            $offeredTitle = trim((string) $this->input('offered_title', ''));
            $offeredValue = $this->input('offered_value');

            if ($offeredListingIds !== []) {
                return;
            }

            if ($offeredTitle === '' || ! is_numeric($offeredValue) || (float) $offeredValue <= 0) {
                $validator->errors()->add(
                    'offered_listing_ids',
                    'Select at least one of your trade listings or provide manual offered card title and value.'
                );
            }
        });
    }
}
