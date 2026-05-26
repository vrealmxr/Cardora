<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'franchise' => ['nullable', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900'],
            'language' => ['nullable', 'string', 'max:100'],
            'set_name' => ['nullable', 'string', 'max:255'],
            'item_number' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'media' => ['nullable', 'array'],
            'authenticity_notes' => ['nullable', 'string'],
            'is_authenticated' => ['nullable', 'boolean'],
            'is_lot' => ['nullable', 'boolean'],
            'lot_configuration' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
