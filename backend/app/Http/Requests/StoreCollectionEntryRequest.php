<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:title'],
            'title' => ['nullable', 'string', 'max:255', 'required_without:product_id'],
            'caption' => ['nullable', 'string'],
            'media' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_featured' => ['sometimes', 'boolean'],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
