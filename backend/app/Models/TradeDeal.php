<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeDeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'trade_request_id',
        'listing_id',
        'owner_user_id',
        'proposer_user_id',
        'dispute_opened_by_user_id',
        'winner_user_id',
        'status',
        'owner_declared_value',
        'proposer_declared_value',
        'deposit_amount',
        'fee_rate',
        'owner_gross_amount',
        'proposer_gross_amount',
        'owner_fee_amount',
        'proposer_fee_amount',
        'owner_net_amount',
        'proposer_net_amount',
        'platform_fee_amount',
        'currency',
        'owner_stripe_checkout_session_id',
        'proposer_stripe_checkout_session_id',
        'owner_stripe_payment_intent_id',
        'proposer_stripe_payment_intent_id',
        'owner_stripe_charge_id',
        'proposer_stripe_charge_id',
        'owner_paid_at',
        'proposer_paid_at',
        'funded_at',
        'owner_released_at',
        'proposer_released_at',
        'settled_at',
        'disputed_at',
        'resolved_at',
        'cancelled_at',
        'resolution',
        'resolution_notes',
        'payout_metadata',
        'metadata',
    ];

    protected $casts = [
        'owner_declared_value' => 'decimal:2',
        'proposer_declared_value' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'fee_rate' => 'decimal:4',
        'owner_gross_amount' => 'decimal:2',
        'proposer_gross_amount' => 'decimal:2',
        'owner_fee_amount' => 'decimal:2',
        'proposer_fee_amount' => 'decimal:2',
        'owner_net_amount' => 'decimal:2',
        'proposer_net_amount' => 'decimal:2',
        'platform_fee_amount' => 'decimal:2',
        'owner_paid_at' => 'datetime',
        'proposer_paid_at' => 'datetime',
        'funded_at' => 'datetime',
        'owner_released_at' => 'datetime',
        'proposer_released_at' => 'datetime',
        'settled_at' => 'datetime',
        'disputed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payout_metadata' => 'array',
        'metadata' => 'array',
    ];

    public function tradeRequest(): BelongsTo
    {
        return $this->belongsTo(TradeRequest::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_user_id');
    }

    public function disputeOpenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispute_opened_by_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function isParticipant(int $userId): bool
    {
        return in_array($userId, [(int) $this->owner_user_id, (int) $this->proposer_user_id], true);
    }

    public function participantRole(int $userId): ?string
    {
        if ((int) $this->owner_user_id === $userId) {
            return 'owner';
        }

        if ((int) $this->proposer_user_id === $userId) {
            return 'proposer';
        }

        return null;
    }

    public function bothPaid(): bool
    {
        return $this->owner_paid_at !== null && $this->proposer_paid_at !== null;
    }

    public function bothReleased(): bool
    {
        return $this->owner_released_at !== null && $this->proposer_released_at !== null;
    }
}
