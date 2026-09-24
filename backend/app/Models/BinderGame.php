<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BinderGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'catalog_group',
        'catalog_status',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function sets(): HasMany
    {
        return $this->hasMany(BinderSet::class, 'game_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(BinderCard::class, 'game_id');
    }
}
