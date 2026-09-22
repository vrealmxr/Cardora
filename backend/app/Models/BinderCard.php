<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BinderCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'set_id',
        'game_id',
        'external_product_id',
        'card_key',
        'oracle_id',
        'name',
        'clean_name',
        'number',
        'rarity',
        'card_type',
        'is_promo',
        'is_token',
        'language',
        'image_url',
    ];

    protected $casts = [
        'external_product_id' => 'integer',
        'is_promo' => 'boolean',
        'is_token' => 'boolean',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(BinderSet::class, 'set_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(BinderGame::class, 'game_id');
    }

    /**
     * @deprecated Ownership is moving to variant level
     * ({@see BinderCardVariant}) — a collector cares whether they own the
     * holo or not, not just "a" Charizard. Kept until binder_user_cards is
     * migrated to point at variant_id.
     */
    public function userCards(): HasMany
    {
        return $this->hasMany(BinderUserCard::class, 'card_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(BinderCardVariant::class, 'card_id')->orderBy('sort_order');
    }

    /**
     * Additive, separate from {@see set()}: the same canonical printing can
     * be sold as part of more than one retail product/release (e.g. the
     * same Blue-Eyes White Dragon promo shipping in both "Kaiba's Collector
     * Box" and "Yugi & Kaiba Collector Box") without that being a second
     * canonical card. set_id stays the primary/canonical checklist grouping.
     */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(BinderRelease::class, 'binder_card_release_memberships', 'card_id', 'release_id')
            ->withPivot(['membership_type', 'source_provider', 'source_reference', 'notes'])
            ->withTimestamps();
    }

    /**
     * No FK — see {@see BinderCardVariant::externalIds()}. Card-level
     * mappings are for providers that key at the canonical-card level
     * (e.g. Scrydex), not per-printing.
     */
    public function externalIds(): HasMany
    {
        return $this->hasMany(BinderCardExternalId::class, 'entity_key', 'card_key')
            ->where('entity_type', 'card');
    }
}
