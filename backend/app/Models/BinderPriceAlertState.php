<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinderPriceAlertState extends Model
{
    use HasFactory;

    protected $table = 'binder_price_alert_state';

    protected $primaryKey = 'binder_card_id';

    public $incrementing = false;

    protected $fillable = [
        'binder_card_id',
        'last_alert_price',
        'last_alert_at',
    ];

    protected $casts = [
        'last_alert_price' => 'decimal:2',
        'last_alert_at' => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(BinderCard::class, 'binder_card_id');
    }
}
