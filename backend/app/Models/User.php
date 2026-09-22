<?php

namespace App\Models;

use App\Notifications\CardoraResetPasswordNotification;
use App\Notifications\CardoraVerifyEmailNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'handle',
        'email',
        'phone',
        'city',
        'shipping_origin',
        'bio',
        'collector_tagline',
        'profile_visibility',
        'avatar_url',
        'profile_cover',
        'notification_preferences',
        'locale',
        'favorite_categories',
        'trust_status',
        'rating',
        'sales_count',
        'purchase_count',
        'is_verified_seller',
        'is_admin',
        'is_seo_editor',
        'admin_role',
        'admin_notes',
        'admin_last_seen_at',
        'last_seen_at',
        'password',
        'stripe_customer_id',
        'pro_status',
        'pro_current_period_end',
        'pro_cancel_at_period_end',
        'pro_trial_used_at',
        'pro_featured_credit_available',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'favorite_categories' => 'array',
        'profile_cover' => 'array',
        'shipping_origin' => 'array',
        'notification_preferences' => 'array',
        'rating' => 'decimal:2',
        'is_verified_seller' => 'boolean',
        'is_admin' => 'boolean',
        'is_seo_editor' => 'boolean',
        'admin_last_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'password' => 'hashed',
        'pro_current_period_end' => 'datetime',
        'pro_cancel_at_period_end' => 'boolean',
        'pro_trial_used_at' => 'datetime',
        'pro_featured_credit_available' => 'boolean',
    ];

    /**
     * Whether the user currently has an active (or trialing) Cardora PRO
     * subscription. This is the single check every PRO-gated feature
     * (fees, scanner quota, alert caps, analytics...) should use.
     */
    public function isProActive(): bool
    {
        return in_array($this->pro_status, ['trialing', 'active'], true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && ($this->is_admin || $this->is_seo_editor);
    }

    public function getFilamentName(): string
    {
        return $this->display_name ?: $this->handle ?: $this->name;
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'seller_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function collectionEntries(): HasMany
    {
        return $this->hasMany(CollectionEntry::class);
    }

    public function profileLikesGiven(): HasMany
    {
        return $this->hasMany(ProfileLike::class);
    }

    public function profileLikesReceived(): HasMany
    {
        return $this->hasMany(ProfileLike::class, 'profile_user_id');
    }

    public function profileFollowsGiven(): HasMany
    {
        return $this->hasMany(UserFollow::class);
    }

    public function profileFollowersReceived(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'followed_user_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function buyerConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function sellerConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function writtenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function marketplaceNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function sellerPayoutAccount(): HasOne
    {
        return $this->hasOne(SellerPayoutAccount::class, 'seller_id');
    }

    public function sellerBalance(): HasOne
    {
        return $this->hasOne(SellerBalance::class, 'seller_id');
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class, 'seller_id');
    }

    public function verificationSubmissions(): HasMany
    {
        return $this->hasMany(VerificationSubmission::class);
    }

    public function authoredBlogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id');
    }

    public function hostedDrawCampaigns(): HasMany
    {
        return $this->hasMany(DrawCampaign::class, 'host_user_id');
    }

    public function wonDrawCampaigns(): HasMany
    {
        return $this->hasMany(DrawCampaign::class, 'winner_user_id');
    }

    public function drawEntries(): HasMany
    {
        return $this->hasMany(DrawEntry::class);
    }

    public function auctionBids(): HasMany
    {
        return $this->hasMany(AuctionBid::class, 'bidder_id');
    }

    public function wonAuctionListings(): HasMany
    {
        return $this->hasMany(Listing::class, 'winning_bidder_id');
    }

    public function sentTradeRequests(): HasMany
    {
        return $this->hasMany(TradeRequest::class, 'requester_user_id');
    }

    public function receivedTradeRequests(): HasMany
    {
        return $this->hasMany(TradeRequest::class, 'listing_owner_user_id');
    }

    public function tradeDealsAsOwner(): HasMany
    {
        return $this->hasMany(TradeDeal::class, 'owner_user_id');
    }

    public function tradeDealsAsProposer(): HasMany
    {
        return $this->hasMany(TradeDeal::class, 'proposer_user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CardoraVerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CardoraResetPasswordNotification($token));
    }

    public function getAvatarUrlAttribute(?string $value): ?string
    {
        if (blank($value)) {
            return $value;
        }

        if (! Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $host = parse_url($value, PHP_URL_HOST);
        $path = parse_url($value, PHP_URL_PATH) ?: '';

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return $value;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '') {
            return $value;
        }

        return $appUrl . $path;
    }

    public function issueSingleSessionToken(string $tokenName): string
    {
        return DB::transaction(function () use ($tokenName): string {
            $lockedUser = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->tokens()->delete();

            return $lockedUser->createToken($tokenName)->plainTextToken;
        });
    }
}
