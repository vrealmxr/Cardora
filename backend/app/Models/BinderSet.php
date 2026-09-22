<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BinderSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'external_group_id',
        'slug',
        'set_key',
        'source',
        'source_set_id',
        'source_url',
        'name',
        'abbreviation',
        'set_code',
        'set_type',
        'language',
        'region',
        'released_at',
        'card_count',
        'official_total',
        'printed_total',
    ];

    protected $casts = [
        'external_group_id' => 'integer',
        'released_at' => 'datetime',
        'card_count' => 'integer',
        'official_total' => 'integer',
        'printed_total' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(BinderGame::class, 'game_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(BinderCard::class, 'set_id');
    }
}
