<?php

namespace App\Http\Requests;

use App\Filament\Support\MarketplaceAdminOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'exists:orders,id'],
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100', Rule::in(array_keys(MarketplaceAdminOptions::supportCategories()))],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50', Rule::in(array_keys(MarketplaceAdminOptions::supportPriorities()))],
            'description' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'metadata.reported_content_type' => ['nullable', 'string', 'max:100'],
            'metadata.reported_url' => ['nullable', 'string', 'max:2048'],
            'metadata.reported_user_reference' => ['nullable', 'string', 'max:255'],
            'metadata.notice_reason' => ['nullable', 'string', 'max:255'],
            'metadata.requested_action' => ['nullable', 'string', 'max:255'],
            'metadata.good_faith_confirmed' => ['nullable', 'boolean'],
            'metadata.accuracy_confirmed' => ['nullable', 'boolean'],
            'metadata.supporting_context' => ['nullable', 'string'],
            'metadata.reporter_email' => ['nullable', 'email:rfc'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('category') !== 'dsa_notice') {
                return;
            }

            $metadata = $this->input('metadata', []);

            if (blank($metadata['reported_content_type'] ?? null)) {
                $validator->errors()->add('metadata.reported_content_type', 'Reported content type is required.');
            }

            if (blank($metadata['reported_url'] ?? null) && blank($metadata['reported_user_reference'] ?? null)) {
                $validator->errors()->add('metadata.reported_url', 'A reported URL or user reference is required.');
            }

            if (blank($metadata['notice_reason'] ?? null)) {
                $validator->errors()->add('metadata.notice_reason', 'Notice reason is required.');
            }

            if (! filter_var($metadata['good_faith_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add('metadata.good_faith_confirmed', 'Good faith confirmation is required.');
            }

            if (! filter_var($metadata['accuracy_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add('metadata.accuracy_confirmed', 'Accuracy confirmation is required.');
            }
        });
    }
}
