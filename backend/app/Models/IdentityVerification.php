<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerification extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['verified_at' => 'datetime', 'expires_at' => 'datetime', 'migrated_at' => 'datetime', 'legacy_snapshot' => 'array'];
    protected $hidden = ['legacy_snapshot'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
