<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinderCardPricePoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'binder_card_id',
        'listing_id',
        'price',
        'currency',
        'source',
        'recorded_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(BinderCard::class, 'binder_card_id');
    }
}
