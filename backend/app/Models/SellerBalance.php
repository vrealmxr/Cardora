<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'pending_amount',
        'available_amount',
        'paid_out_amount',
        'currency',
    ];

    protected $casts = [
        'pending_amount' => 'decimal:2',
        'available_amount' => 'decimal:2',
        'paid_out_amount' => 'decimal:2',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class, 'seller_id', 'seller_id');
    }
}
