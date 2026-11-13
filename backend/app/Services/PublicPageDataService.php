<?php

namespace App\Services;

use App\Models\Category;
use App\Models\DrawCampaign;
use App\Models\Listing;
use App\Models\User;
use App\Support\CardoraStaticContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/** Anonymous rendering data. Never call build(): that includes account data and campaign writes. */
class PublicPageDataService extends MarketplaceBootstrapService
{
    private ?PublicMediaDimensions $mediaDimensions = null;

    protected function normalizeMediaItem(mixed $item, int $index): ?array
    {
        $media = parent::normalizeMediaItem($item, $index);
        if (!$media) return null;
        $this->mediaDimensions ??= new PublicMediaDimensions(Storage::disk('public')->path('uploads/listings'));
        $media += $this->mediaDimensions->forUrl($media['url'] ?? null);
        $thumb = $this->mediaDimensions->forUrl($media['thumbUrl'] ?? null);
        if ($thumb) {
            $media['thumbWidth'] = $thumb['width'];
            $media['thumbHeight'] = $thumb['height'];
        }
        return $media;
    }

    public function snapshot(string $locale): array
    {
        $categories = $this->buildCategories(Category::query()->whereNull('parent_id')
            ->orderBy('sort_order')->orderBy('name')->get(), $locale);
        $byKey = collect($categories)->keyBy('id');
        $keysById = $byKey->mapWithKeys(fn (array $row) => [(int) $row['databaseId'] => $row['id']]);
        $listings = Listing::query()->whereIn('status', ['active', 'published'])
            ->with(['product.category', 'category', 'seller'])
            ->withCount(['favorites', 'bids'])->latest('published_at')->latest('created_at')->get()
            ->filter(fn (Listing $listing) => app(MarketplaceAccessService::class)->listingIsPubliclyVisible($listing))->values();
        $products = $listings->map(function (Listing $listing) use ($locale, $keysById, $byKey) {
            $item = $this->presentListingAsProduct($listing, $locale, $keysById, $byKey);
            $item['description'] = $listing->product?->description ?? '';
            $item['shortDescription'] = $listing->product?->subtitle ?? '';
            $item['status'] = $listing->status;
            $item['updatedAt'] = optional($listing->updated_at)->toIso8601String();
            return self::publicProduct($item);
        })->all();
        // Public sellers only; no buyer directory, private collection, email, identity or payout data.
        $sellers = $listings->pluck('seller')->filter()->unique('id')->values();
        $users = $sellers->map(function (User $user) use ($locale, $byKey) {
            $item = $this->presentUser($user, $locale, $byKey);
            $item['name'] = $item['displayName'] = $user->display_name ?: $user->handle ?: 'Collector';
            return self::publicSeller($item, $user->profile_visibility === 'public');
        })->all();
        $home = CardoraStaticContent::home($locale);
        $support = CardoraStaticContent::support($locale);
        $draws = CardoraStaticContent::draws($locale);
        $blog = $this->buildBlogPosts($locale);

        return [
            'categories' => $categories, 'products' => $products, 'users' => $users,
            'shippingSettings' => [
                'dhl' => ['enabled' => (bool) config('services.dhl.enabled', false)],
                'boxnow' => ['enabled' => (bool) config('services.boxnow.enabled', false),
                    'listing_edit_enabled' => (bool) config('services.boxnow.listing_edit_enabled', false)],
            ],
            'reviews' => $home['reviews'], 'trustHighlights' => $home['trust_highlights'],
            'howItWorksSteps' => $home['how_it_works'],
            'supportArticles' => $support['articles'], 'faqGroups' => $support['faq_groups'],
            'blogCategories' => $this->buildBlogCategories($blog, $locale), 'blogPosts' => $blog,
            'drawCampaigns' => $this->publicCampaigns(), 'drawHighlights' => $draws['highlights'],
            'drawHowItWorks' => $draws['how_it_works'], 'drawFaq' => $draws['faq'],
            'platformStats' => $this->buildPlatformStats($listings, $sellers, $locale),
        ];
    }

    public static function publicSeller(array $item, bool $publicProfile): array
    {
        $safe = Arr::only($item, ['id', 'name', 'displayName', 'handle', 'avatar', 'verified',
            'rating', 'salesCount', 'soldItems', 'listedItems', 'trustStatus']);
        if ($publicProfile) {
            $safe += Arr::only($item, ['bio', 'specializations', 'collectorTagline']);
            $city = (string) ($item['city'] ?? '');
            $safe['city'] = str_contains($city, '@') ? '' : $city;
        }
        // No fabricated reply-time promise when no measured response-time data exists.
        $safe['responseTime'] = '';
        return $safe;
    }

    public static function publicProduct(array $item): array
    {
        $safe = Arr::only($item, [
            'id', 'databaseProductId', 'listingId', 'slug', 'title', 'subtitle', 'categoryId',
            'franchise', 'series', 'brand', 'typeLabel', 'rarity', 'condition', 'price', 'oldPrice',
            'minimumOffer', 'acceptOffers', 'shippingCost', 'shippingProfile', 'deliveryCarrier',
            'deliveryOptions', 'shippingCostByCarrier', 'stock', 'availability', 'sellerId',
            'sellerIsPro', 'sellerRating', 'listedAt', 'year', 'language', 'setName', 'cardNumber',
            'gradedCompany', 'grade', 'returns', 'visual', 'tags', 'featured', 'featuredUntil',
            'saleFormat', 'description', 'shortDescription', 'status', 'updatedAt',
        ]);
        $safe['media'] = array_map(fn (array $media) => Arr::only($media,
            ['type', 'url', 'thumbUrl', 'thumbnailUrl', 'thumbnail_url', 'alt', 'width', 'height', 'thumbWidth', 'thumbHeight']), $item['media'] ?? []);
        $safe['auction'] = empty($item['auction']) ? null : Arr::only($item['auction'], [
            'startingBid', 'currentBid', 'reservePrice', 'bidIncrement', 'bidCount', 'watchers', 'buyoutPrice', 'endsAt', 'startsAt', 'winningBidderId',
        ]);
        $safe['lot'] = empty($item['lot']) ? null : Arr::only($item['lot'], [
            'totalCards', 'guaranteedHits', 'previewCards', 'themes', 'note', 'overflowCount',
            'allowsIndividualPurchase', 'wholeLotPurchaseAvailable', 'availableIndividualCardsCount',
        ]);
        if ($safe['lot'] !== null) {
            $safe['lot']['individualCards'] = array_map(fn (array $card) => Arr::only($card,
                ['id', 'title', 'price']), $item['lot']['individualCards'] ?? []);
        }
        return $safe;
    }

    private function publicCampaigns(): array
    {
        // Read stored public progress. Campaign synchronization belongs to its existing job/flow.
        return DrawCampaign::query()->whereIn('status', ['active', 'locked', 'completed'])
            ->with('hostUser')->latest('created_at')->get()->map(function (DrawCampaign $draw) {
                $visual = is_array($draw->visual) ? $draw->visual : [];
                $image = $visual['imageUrl'] ?? null;
                if (!$image && !empty($visual['imagePath'])) $image = Storage::disk('public')->url($visual['imagePath']);
                return [
                    'id' => (int) $draw->id, 'campaignType' => $draw->campaign_type,
                    'title' => $draw->title, 'slug' => $draw->slug, 'subtitle' => $draw->subtitle,
                    'prizeTitle' => $draw->prize_title, 'prizeCategory' => $draw->prize_category,
                    'prizeCondition' => $draw->prize_condition, 'prizeValue' => (float) $draw->prize_value,
                    'entryPrice' => $draw->entry_price ? (float) $draw->entry_price : null,
                    'entriesPerEuro' => $draw->entries_per_euro, 'targetAmount' => (float) $draw->target_amount,
                    'currentAmount' => (float) $draw->current_amount, 'targetEntries' => $draw->target_entries,
                    'soldEntries' => (int) $draw->sold_entries, 'participants' => (int) $draw->participants_count,
                    'maxEntriesPerUser' => $draw->max_entries_per_user,
                    'hostedBy' => $draw->hostUser?->display_name ?: $draw->hostUser?->handle,
                    'hostUserId' => $draw->host_user_id, 'status' => $draw->status,
                    'featured' => (bool) $draw->featured, 'requiresVerification' => (bool) $draw->requires_verification,
                    'fairnessNote' => $draw->fairness_note, 'dispatchWindow' => $draw->dispatch_window,
                    'endsAt' => optional($draw->ends_at)->toIso8601String(),
                    'drawAt' => optional($draw->draw_at)->toIso8601String(), 'imageUrl' => $image,
                    'visual' => Arr::only($visual, ['gradient', 'label']) + ['imageUrl' => $image],
                ];
            })->all();
    }
}
