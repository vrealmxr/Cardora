<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BinderCardExternalId extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_key',
        'provider',
        'external_id',
        'external_type',
        'external_url',
    ];
}
