<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardScan extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'images'=>'array', 'prediction'=>'array', 'review'=>'array', 'clearance'=>'array', 'provenance'=>'array',
        'training_opt_in'=>'boolean', 'expires_at'=>'datetime', 'started_at'=>'datetime',
    ];
}
