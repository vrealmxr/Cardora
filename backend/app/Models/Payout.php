<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'escrow_transaction_id',
        'amount',
        'currency',
        'status',
        'reference',
        'stripe_transfer_id',
        'stripe_payout_id',
        'connected_account_id',
        'initiated_at',
        'requested_at',
        'completed_at',
        'processed_at',
        'arrival_date',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'processed_at' => 'datetime',
        'arrival_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function escrowTransaction(): BelongsTo
    {
        return $this->belongsTo(EscrowTransaction::class);
    }
}
