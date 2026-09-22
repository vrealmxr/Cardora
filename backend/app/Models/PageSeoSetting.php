<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSeoSetting extends Model
{
    protected $fillable = [
        'page_key',
        'locale',
        'meta_title',
        'meta_description',
        'h1',
    ];
}
