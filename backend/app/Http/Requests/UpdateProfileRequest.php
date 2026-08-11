<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'handle' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('users', 'handle')->ignore($this->user()?->getKey()),
            ],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string'],
            'collector_tagline' => ['nullable', 'string', 'max:255'],
            'shipping_origin' => ['nullable', 'array'],
            'shipping_origin.company_name' => ['nullable', 'string', 'max:255'],
            'shipping_origin.full_name' => ['nullable', 'string', 'max:255'],
            'shipping_origin.phone' => ['nullable', 'string', 'max:50'],
            'shipping_origin.address_line_1' => ['nullable', 'string', 'max:255'],
            'shipping_origin.address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_origin.city' => ['nullable', 'string', 'max:100'],
            'shipping_origin.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_origin.country' => ['nullable', 'string', 'max:120'],
            'shipping_origin.country_code' => ['nullable', 'string', 'size:2'],
            'profile_visibility' => ['nullable', Rule::in(['public', 'private'])],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'profile_cover' => ['nullable', 'array'],
            'profile_cover.palette' => ['nullable', 'array'],
            'profile_cover.palette.from' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'profile_cover.palette.via' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'profile_cover.palette.to' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.messages' => ['nullable', 'array'],
            'notification_preferences.messages.in_app' => ['nullable', 'boolean'],
            'notification_preferences.messages.email' => ['nullable', 'boolean'],
            'notification_preferences.orders' => ['nullable', 'array'],
            'notification_preferences.orders.in_app' => ['nullable', 'boolean'],
            'notification_preferences.orders.email' => ['nullable', 'boolean'],
            'notification_preferences.follows' => ['nullable', 'array'],
            'notification_preferences.follows.in_app' => ['nullable', 'boolean'],
            'notification_preferences.follows.email' => ['nullable', 'boolean'],
            'notification_preferences.support' => ['nullable', 'array'],
            'notification_preferences.support.in_app' => ['nullable', 'boolean'],
            'notification_preferences.support.email' => ['nullable', 'boolean'],
            'notification_preferences.security' => ['nullable', 'array'],
            'notification_preferences.security.in_app' => ['nullable', 'boolean'],
            'notification_preferences.security.email' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(config('app.supported_locales', ['el', 'en']))],
            'favorite_categories' => ['nullable', 'array'],
            'trust_status' => ['nullable', 'string', 'max:100'],
        ];
    }
}
