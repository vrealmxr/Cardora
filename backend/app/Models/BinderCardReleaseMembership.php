<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinderCardReleaseMembership extends Model
{
    protected $fillable = [
        'card_id',
        'variant_id',
        'release_id',
        'membership_type',
        'source_provider',
        'source_reference',
        'notes',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(BinderCard::class, 'card_id');
    }

    /** Nullable -- a membership can be card-level only (variant unknown) or scoped to one specific printing/artwork. */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(BinderCardVariant::class, 'variant_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(BinderRelease::class, 'release_id');
    }
}
