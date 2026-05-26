<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionBid extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'bidder_id',
        'amount',
        'placed_at',
        'ip_address',
        'user_agent',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'placed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }
}
