<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingOffer extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COUNTERED = 'countered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'listing_id',
        'conversation_id',
        'buyer_id',
        'seller_id',
        'initiator_id',
        'recipient_id',
        'parent_offer_id',
        'order_id',
        'status',
        'sequence',
        'item_amount',
        'shipping_amount',
        'total_amount',
        'commission_amount',
        'accepted_at',
        'rejected_at',
        'cancelled_at',
        'expired_at',
        'paid_at',
        'last_action_at',
        'metadata',
    ];

    protected $casts = [
        'item_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_action_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function parentOffer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_offer_id');
    }

    public function childOffers(): HasMany
    {
        return $this->hasMany(self::class, 'parent_offer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
