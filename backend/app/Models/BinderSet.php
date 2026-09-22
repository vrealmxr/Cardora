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
        'name',
        'abbreviation',
        'released_at',
        'card_count',
    ];

    protected $casts = [
        'external_group_id' => 'integer',
        'released_at' => 'datetime',
        'card_count' => 'integer',
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
