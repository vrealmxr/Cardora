<?php

namespace App\Http\Resources;

use App\Services\MarketplaceAccessService;
use App\Services\UserNotificationPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $favoriteCategories = $this->favorite_categories ?? [];
        $recentActivity = $this->recentActivity();
        $listedItems = $this->listedItemsCount();
        $soldItems = (int) ($this->sales_count ?? 0);
        $purchasedItems = (int) ($this->purchase_count ?? 0);
        $marketplaceAccess = app(MarketplaceAccessService::class)->summaryForUser($this->resource, app()->getLocale());
        $notificationPreferences = app(UserNotificationPreferenceService::class)->normalize($this->notification_preferences);
        $canViewPrivateProfileFields = (bool) $request->user()
            && (
                (int) $request->user()->getKey() === (int) $this->id
                || (bool) $request->user()->is_admin
            );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'displayName' => $this->display_name ?: $this->handle ?: $this->name,
            'handle' => $this->handle,
            'email' => $this->email,
            'phone' => $this->when($canViewPrivateProfileFields, $this->phone),
            'email_verified_at' => $this->email_verified_at,
            'emailVerifiedAt' => $this->email_verified_at,
            'email_verified' => (bool) $this->hasVerifiedEmail(),
            'emailVerified' => (bool) $this->hasVerifiedEmail(),
            'city' => $this->city,
            'bio' => $this->bio,
            'collector_tagline' => $this->collector_tagline,
            'collectorTagline' => $this->collector_tagline,
            'avatar_url' => $this->avatar_url,
            'profile_cover' => $this->profile_cover,
            'shipping_origin' => $this->when($canViewPrivateProfileFields, $this->shipping_origin),
            'shippingOrigin' => $this->when($canViewPrivateProfileFields, $this->shipping_origin),
            'notification_preferences' => $notificationPreferences,
            'notificationPreferences' => $notificationPreferences,
            'profile_visibility' => $this->profile_visibility,
            'locale' => $this->locale,
            'favorite_categories' => $favoriteCategories,
            'favoriteCategories' => $favoriteCategories,
            'trust_status' => $this->trust_status,
            'trustStatus' => $this->localizedTrustStatus(),
            'rating' => $this->rating,
            'sales_count' => $this->sales_count,
            'purchase_count' => $this->purchase_count,
            'listed_items' => $listedItems,
            'listedItems' => $listedItems,
            'sold_items' => $soldItems,
            'soldItems' => $soldItems,
            'purchased_items' => $purchasedItems,
            'purchasedItems' => $purchasedItems,
            'recent_activity' => $recentActivity,
            'recentActivity' => $recentActivity,
            'response_time' => $this->localizedResponseTime(),
            'responseTime' => $this->localizedResponseTime(),
            'is_verified_seller' => $this->is_verified_seller,
            'verified' => (bool) $this->is_verified_seller,
            'marketplace_access' => $marketplaceAccess,
            'marketplaceAccess' => $marketplaceAccess,
            'stripe_connect' => $this->whenLoaded('sellerPayoutAccount', function () {
                return [
                    'stripe_account_id' => $this->sellerPayoutAccount?->stripe_account_id,
                    'onboarding_completed' => (bool) $this->sellerPayoutAccount?->onboarding_completed,
                    'charges_enabled' => (bool) $this->sellerPayoutAccount?->charges_enabled,
                    'payouts_enabled' => (bool) $this->sellerPayoutAccount?->payouts_enabled,
                    'details_submitted' => (bool) $this->sellerPayoutAccount?->details_submitted,
                    'country' => $this->sellerPayoutAccount?->country,
                    'default_currency' => $this->sellerPayoutAccount?->default_currency,
                ];
            }),
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function listedItemsCount(): int
    {
        if (isset($this->listings_count)) {
            return (int) $this->listings_count;
        }

        if ($this->relationLoaded('listings')) {
            return $this->listings->count();
        }

        return method_exists($this->resource, 'listings')
            ? $this->resource->listings()->count()
            : 0;
    }

    protected function recentActivity(): array
    {
        $metadata = Arr::get($this->profile_cover ?? [], 'metadata', []);
        $locale = app()->getLocale();
        $localized = Arr::get($metadata, sprintf('translations.%s.recent_activity', $locale));

        if (is_array($localized)) {
            return array_values(array_filter($localized));
        }

        $fallback = Arr::get($metadata, 'recent_activity', []);

        return is_array($fallback)
            ? array_values(array_filter($fallback))
            : [];
    }

    protected function localizedResponseTime(): string
    {
        $metadata = Arr::get($this->profile_cover ?? [], 'metadata', []);
        $locale = app()->getLocale();
        $localized = Arr::get($metadata, sprintf('translations.%s.response_time', $locale));

        if (is_string($localized) && $localized !== '') {
            return $localized;
        }

        $fallback = Arr::get($metadata, 'response_time');

        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return $locale === 'en'
            ? 'Response time will appear after the first conversations.'
            : 'Ο χρόνος απάντησης θα εμφανιστεί μετά τις πρώτες συνομιλίες.';
    }

    protected function localizedTrustStatus(): string
    {
        if ($this->is_verified_seller) {
            return app()->getLocale() === 'en'
                ? 'Verified seller'
                : 'Επαληθευμένος πωλητής';
        }

        return match ((string) $this->trust_status) {
            'trusted' => app()->getLocale() === 'en' ? 'Trusted account' : 'Αξιόπιστος λογαριασμός',
            'basic' => app()->getLocale() === 'en' ? 'Basic account' : 'Βασικός λογαριασμός',
            default => (string) ($this->trust_status ?: (app()->getLocale() === 'en' ? 'Basic account' : 'Βασικός λογαριασμός')),
        };
    }
}
