<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:users,handle'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'city' => ['required', 'string', 'max:255'],
            'collector_tagline' => ['required', 'string', 'max:255'],
            'favorite_categories' => ['required', 'array', 'min:1'],
            'favorite_categories.*' => ['string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['nullable', Rule::in(config('app.supported_locales', ['el', 'en']))],
            'terms' => ['required', 'accepted'],
            'privacy' => ['required', 'accepted'],
            'marketing' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
