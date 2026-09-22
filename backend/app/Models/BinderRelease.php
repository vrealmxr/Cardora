<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BinderRelease extends Model
{
    protected $fillable = [
        'game_id',
        'release_key',
        'release_name',
        'release_type',
        'region_code',
        'released_at',
        'source_provider',
        'source_external_id',
        'notes',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(BinderGame::class, 'game_id');
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(BinderCard::class, 'binder_card_release_memberships', 'release_id', 'card_id')
            ->withPivot(['membership_type', 'source_provider', 'source_reference', 'notes'])
            ->withTimestamps();
    }
}
