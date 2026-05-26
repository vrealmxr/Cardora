<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                'pending_payment',
                'paid_pending_release',
                'released',
                'disputed',
                'refunded',
                'cancelled',
            ])],
            'escrow_status' => ['nullable', 'string', 'max:50'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'disputed_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
