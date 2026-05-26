<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'draw_campaign_id',
        'user_id',
        'order_id',
        'entries',
        'amount',
        'source_type',
        'source_reference',
        'status',
        'metadata',
        'entered_at',
    ];

    protected $casts = [
        'entries' => 'integer',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'entered_at' => 'datetime',
    ];

    public function drawCampaign(): BelongsTo
    {
        return $this->belongsTo(DrawCampaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
