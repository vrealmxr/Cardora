<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAttributeDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'taxonomy_id',
        'name',
        'slug',
        'field_type',
        'options',
        'placeholder',
        'help_text',
        'default_value',
        'is_required',
        'is_filterable',
        'applies_to_product',
        'applies_to_listing',
        'status',
        'sort_order',
        'validation_rules',
        'metadata',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'applies_to_product' => 'boolean',
        'applies_to_listing' => 'boolean',
        'sort_order' => 'integer',
        'validation_rules' => 'array',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(CategoryTaxonomy::class, 'taxonomy_id');
    }
}
