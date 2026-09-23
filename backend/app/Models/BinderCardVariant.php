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
        'source_variant_id',
        'source_variant_kind',
        'variant_name',
        'variant_type',
        'rarity',
        'printed_effect_text',
        'region_code',
        'edition_code',
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

    public function releaseMemberships(): HasMany
    {
        return $this->hasMany(BinderCardReleaseMembership::class, 'variant_id');
    }

    /**
     * No FK — binder_card_external_ids is keyed by (entity_type,
     * entity_key), not a numeric column, since one provider mapping can
     * point at either a card or a variant.
     */
    public function externalIds(): HasMany
    {
        return $this->hasMany(BinderCardExternalId::class, 'entity_key', 'variant_key')
            ->where('entity_type', 'variant');
    }
}
