<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'binder_card_id',
        'title',
        'slug',
        'sku',
        'subtitle',
        'franchise',
        'series',
        'brand',
        'year',
        'language',
        'set_name',
        'item_number',
        'description',
        'specifications',
        'tags',
        'media',
        'authenticity_notes',
        'is_authenticated',
        'is_lot',
        'lot_configuration',
        'product_type',
        'metadata',
    ];

    protected $casts = [
        'year' => 'integer',
        'specifications' => 'array',
        'tags' => 'array',
        'media' => 'array',
        'is_authenticated' => 'boolean',
        'is_lot' => 'boolean',
        'lot_configuration' => 'array',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function binderCard(): BelongsTo
    {
        return $this->belongsTo(BinderCard::class, 'binder_card_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function collectionEntries(): HasMany
    {
        return $this->hasMany(CollectionEntry::class);
    }
}
