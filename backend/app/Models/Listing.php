<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'category_id',
        'seller_id',
        'title_snapshot',
        'price',
        'old_price',
        'minimum_offer',
        'quantity',
        'available_quantity',
        'condition',
        'rarity',
        'status',
        'sale_format',
        'shipping_cost',
        'shipping_profile',
        'shipping_methods',
        'dispatch_time',
        'packaging_notes',
        'availability',
        'accept_offers',
        'is_featured',
        'featured_until',
        'featured_payment_id',
        'auction_settings',
        'starting_bid',
        'current_bid',
        'reserve_price',
        'bid_increment',
        'buyout_price',
        'auction_starts_at',
        'auction_ends_at',
        'winning_bidder_id',
        'lot_snapshot',
        'published_at',
        'followers_notified_at',
        'binder_alert_notified_at',
        'expires_at',
        'attributes',
        'compliance_flags',
        'moderation_notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'minimum_offer' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_methods' => 'array',
        'auction_settings' => 'array',
        'starting_bid' => 'decimal:2',
        'current_bid' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'bid_increment' => 'decimal:2',
        'buyout_price' => 'decimal:2',
        'auction_starts_at' => 'datetime',
        'auction_ends_at' => 'datetime',
        'lot_snapshot' => 'array',
        'accept_offers' => 'boolean',
        'is_featured' => 'boolean',
        'featured_until' => 'datetime',
        'published_at' => 'datetime',
        'followers_notified_at' => 'datetime',
        'binder_alert_notified_at' => 'datetime',
        'expires_at' => 'datetime',
        'attributes' => 'array',
        'compliance_flags' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function winningBidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winning_bidder_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ListingOffer::class);
    }

    public function featuredPayment(): BelongsTo
    {
        return $this->belongsTo(FeaturedListingPayment::class, 'featured_payment_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function prizeDrawCampaigns(): HasMany
    {
        return $this->hasMany(DrawCampaign::class, 'prize_listing_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function tradeRequests(): HasMany
    {
        return $this->hasMany(TradeRequest::class);
    }

    public function tradeDeals(): HasMany
    {
        return $this->hasMany(TradeDeal::class);
    }
}
