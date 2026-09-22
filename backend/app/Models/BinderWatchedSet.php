<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinderWatchedSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'set_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(BinderSet::class, 'set_id');
    }
}
