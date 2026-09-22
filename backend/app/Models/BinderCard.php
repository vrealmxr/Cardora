<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BinderCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'set_id',
        'game_id',
        'external_product_id',
        'name',
        'clean_name',
        'number',
        'rarity',
        'card_type',
        'image_url',
    ];

    protected $casts = [
        'external_product_id' => 'integer',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(BinderSet::class, 'set_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(BinderGame::class, 'game_id');
    }

    public function userCards(): HasMany
    {
        return $this->hasMany(BinderUserCard::class, 'card_id');
    }
}
