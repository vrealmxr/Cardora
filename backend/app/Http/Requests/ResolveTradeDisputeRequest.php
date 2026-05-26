<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveTradeDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'winner_user_id' => ['required', 'exists:users,id'],
            'resolution_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
