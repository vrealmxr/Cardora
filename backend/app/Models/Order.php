<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'product_id',
        'order_number',
        'status',
        'escrow_status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_transfer_id',
        'subtotal',
        'shipping_total',
        'service_fee',
        'total',
        'total_amount',
        'commission_amount',
        'seller_amount',
        'currency',
        'payment_method',
        'shipping_address',
        'billing_address',
        'tracking_number',
        'notes',
        'placed_at',
        'completed_at',
        'disputed_at',
        'buyer_confirmed_at',
        'auto_release_at',
        'released_at',
        'cancelled_at',
        'refunded_at',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'seller_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'metadata' => 'array',
        'placed_at' => 'datetime',
        'completed_at' => 'datetime',
        'disputed_at' => 'datetime',
        'buyer_confirmed_at' => 'datetime',
        'auto_release_at' => 'datetime',
        'released_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function escrowTransaction(): HasOne
    {
        return $this->hasOne(EscrowTransaction::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    public function drawEntries(): HasMany
    {
        return $this->hasMany(DrawEntry::class);
    }

    public function listingOffers(): HasMany
    {
        return $this->hasMany(ListingOffer::class);
    }

    public function isPendingPayment(): bool
    {
        return $this->status === OrderStatus::PendingPayment->value;
    }

    public function isPaidPendingRelease(): bool
    {
        return $this->status === OrderStatus::PaidPendingRelease->value;
    }

    public function isReleased(): bool
    {
        return $this->status === OrderStatus::Released->value;
    }
}
