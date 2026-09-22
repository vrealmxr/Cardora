<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BinderCardVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'variant_key',
        'variant_name',
        'variant_type',
        'rarity',
        'artist',
        'image_small',
        'image_large',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(BinderCard::class, 'card_id');
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(BinderCardExternalId::class, 'variant_id');
    }
}
