<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BinderCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'set_id',
        'game_id',
        'external_product_id',
        'card_key',
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
}
