<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinderCardExternalId extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'provider',
        'external_id',
        'external_url',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(BinderCardVariant::class, 'variant_id');
    }
}
