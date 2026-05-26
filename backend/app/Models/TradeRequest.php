<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TradeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'requester_user_id',
        'listing_owner_user_id',
        'status',
        'offered_title',
        'offered_description',
        'offered_condition',
        'offered_value',
        'offered_images',
        'offered_listing_ids',
        'target_listing_ids',
        'swap_pairs',
        'offered_metadata',
        'request_message',
        'terms_accepted',
        'accepted_at',
        'rejected_at',
        'cancelled_at',
        'expires_at',
    ];

    protected $casts = [
        'offered_value' => 'decimal:2',
        'offered_images' => 'array',
        'offered_listing_ids' => 'array',
        'target_listing_ids' => 'array',
        'swap_pairs' => 'array',
        'offered_metadata' => 'array',
        'terms_accepted' => 'boolean',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function listingOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'listing_owner_user_id');
    }

    public function tradeDeal(): HasOne
    {
        return $this->hasOne(TradeDeal::class);
    }
}
