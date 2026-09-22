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
        'base_total',
        'numbered_total',
    ];

    protected $casts = [
        'external_group_id' => 'integer',
        'released_at' => 'datetime',
        'card_count' => 'integer',
        'base_total' => 'integer',
        'numbered_total' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(BinderGame::class, 'game_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(BinderCard::class, 'set_id');
    }

    /**
     * No FK — see {@see BinderCardVariant::externalIds()}. Set-level
     * mappings are for providers keyed on the expansion itself (e.g.
     * Scrydex expansion id, TCGplayer group id, Cardmarket expansion id),
     * independent of the Sets sheet's own source/source_set_id columns.
     */
    public function externalIds(): HasMany
    {
        return $this->hasMany(BinderCardExternalId::class, 'entity_key', 'set_key')
            ->where('entity_type', 'set');
    }
}
