<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVerificationSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_type' => ['nullable', 'string', 'max:50'],
            'payload' => ['nullable', 'array'],
            'requirements_snapshot' => ['nullable', 'array'],
            'documents' => ['nullable', 'array'],
            'documents.*.document_type' => ['required_with:documents', 'string', 'max:100'],
            'documents.*.storage_disk' => ['nullable', 'string', 'max:50'],
            'documents.*.storage_path' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.original_name' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.mime_type' => ['nullable', 'string', 'max:100'],
            'documents.*.file_size' => ['nullable', 'integer', 'min:0'],
            'documents.*.metadata' => ['nullable', 'array'],
            'documents.*.uploaded_at' => ['nullable', 'date'],
        ];
    }
}
