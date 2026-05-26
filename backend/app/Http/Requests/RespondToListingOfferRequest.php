<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondToListingOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'total_amount' => ['nullable', 'numeric', 'min:2.50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
