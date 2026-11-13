<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required_without:order_id', 'nullable', 'integer', 'exists:listings,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'initial_message' => ['nullable', 'string', 'max:3000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
