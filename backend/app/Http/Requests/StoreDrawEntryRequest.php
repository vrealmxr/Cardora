<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDrawEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'exists:orders,id'],
            'entries' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
            'source_type' => ['required', Rule::in(['purchase_volume', 'sale_volume', 'ticket_purchase', 'manual_adjustment'])],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'metadata' => ['nullable', 'array'],
            'entered_at' => ['nullable', 'date'],
        ];
    }
}
