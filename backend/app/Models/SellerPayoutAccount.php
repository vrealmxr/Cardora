<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerPayoutAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'stripe_account_id',
        'account_type',
        'onboarding_completed',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        'country',
        'default_currency',
        'raw_requirements',
    ];

    protected $casts = [
        'onboarding_completed' => 'boolean',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'raw_requirements' => 'array',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isFullyOnboarded(): bool
    {
        return (bool) ($this->onboarding_completed && $this->charges_enabled && $this->payouts_enabled && $this->details_submitted);
    }
}
