<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrawCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_user_id',
        'winner_user_id',
        'prize_listing_id',
        'campaign_type',
        'title',
        'slug',
        'subtitle',
        'description',
        'prize_title',
        'prize_category',
        'prize_condition',
        'prize_value',
        'entry_price',
        'entries_per_euro',
        'target_amount',
        'current_amount',
        'target_entries',
        'entries_issued',
        'sold_entries',
        'participants_count',
        'max_entries_per_user',
        'status',
        'featured',
        'requires_verification',
        'shipping_covered',
        'fairness_note',
        'dispatch_window',
        'rules',
        'eligibility',
        'visual',
        'draw_result',
        'locked_at',
        'ends_at',
        'draw_at',
        'drawn_at',
    ];

    protected $casts = [
        'prize_value' => 'decimal:2',
        'entry_price' => 'decimal:2',
        'entries_per_euro' => 'integer',
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_entries' => 'integer',
        'entries_issued' => 'integer',
        'sold_entries' => 'integer',
        'participants_count' => 'integer',
        'max_entries_per_user' => 'integer',
        'featured' => 'boolean',
        'requires_verification' => 'boolean',
        'shipping_covered' => 'boolean',
        'rules' => 'array',
        'eligibility' => 'array',
        'visual' => 'array',
        'draw_result' => 'array',
        'locked_at' => 'datetime',
        'ends_at' => 'datetime',
        'draw_at' => 'datetime',
        'drawn_at' => 'datetime',
    ];

    public function hostUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function prizeListing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'prize_listing_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DrawEntry::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
