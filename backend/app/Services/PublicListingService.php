<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;

/** A current, anonymous listing view; never expose the general ListingResource. */
class PublicListingService extends PublicPageDataService
{
    public function isPublic(Listing $listing): bool
    {
        if ($listing->status === 'sold') {
            return $listing->published_at !== null && $listing->seller instanceof User && $listing->product !== null;
        }
        return in_array($listing->status, ['active', 'published'], true)
            && app(MarketplaceAccessService::class)->listingIsPubliclyVisible($listing);
    }

    private function currentListings()
    {
        // A new hidden/rejected relisting must not revive an older sold page at the same product URL.
        return Listing::query()->whereNotExists(function ($query) {
            $query->selectRaw('1')->from('listings as newer')
                ->whereColumn('newer.product_id', 'listings.product_id')
                ->whereColumn('newer.id', '>', 'listings.id');
        });
    }

    public function findPublic(string $slug, string $locale): ?array
    {
        $listing = $this->currentListings()->where(function ($query) use ($slug) {
            $query->whereHas('product', fn ($product) => $product->where('slug', $slug));
            if (preg_match('/^listing-([1-9][0-9]*)$/', $slug, $matches)) {
                $query->orWhere(fn ($fallback) => $fallback->whereKey((int) $matches[1])->whereDoesntHave('product'));
            }
        })->with(['product.category', 'category', 'seller'])->withCount(['favorites', 'bids'])->first();
        if (!$listing || !$this->isPublic($listing)) return null;

        $categories = $this->buildCategories(Category::query()->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get(), $locale);
        $byKey = collect($categories)->keyBy('id');
        $keysById = $byKey->mapWithKeys(fn (array $row) => [(int) $row['databaseId'] => $row['id']]);
        $item = $this->presentListingAsProduct($listing, $locale, $keysById, $byKey);
        $item['description'] = $listing->product?->description ?? '';
        $item['shortDescription'] = $listing->product?->subtitle ?? '';
        $item['status'] = $listing->status;
        $item['stock'] = $listing->status === 'sold' ? 0 : (int) $listing->available_quantity;
        $item['updatedAt'] = optional($listing->updated_at)->toIso8601String();
        $safe = self::publicProduct($item);
        // These are public descriptive attributes already displayed by the listing page.
        foreach (['publisher', 'issueNumber', 'printing', 'characterName', 'manufacturerLine', 'scale',
            'figureHeight', 'material', 'miniatureSubtype', 'displayStatus', 'boxCondition', 'edition',
            'accessories', 'serialReference', 'bundleContents', 'writer', 'artist', 'coverArtist',
            'signedBy', 'pageCount', 'isbn', 'authenticity', 'shippingInfo'] as $field) {
            $safe[$field] = is_scalar($item[$field] ?? null) ? $item[$field] : '';
        }
        $seller = $this->presentUser($listing->seller, $locale, $byKey);
        $seller['name'] = $seller['displayName'] = $listing->seller->display_name ?: $listing->seller->handle ?: 'Collector';
        $safe['seller'] = self::publicSeller($seller, $listing->seller->profile_visibility === 'public');
        $category = $byKey->get($safe['categoryId']);
        $safe['category'] = $category ? array_intersect_key($category, array_flip(['id', 'slug', 'name'])) : null;
        return $safe;
    }

    public function publicPaths(): array
    {
        $paths = [];
        $this->currentListings()->whereIn('status', ['active', 'published', 'sold'])
            ->with(['seller', 'product'])->chunkById(500, function ($listings) use (&$paths) {
                foreach ($listings as $listing) {
                    if (!$this->isPublic($listing)) continue;
                    $paths[] = ['slug' => $listing->product?->slug ?: 'listing-'.$listing->id,
                        'updatedAt' => optional($listing->updated_at)->toIso8601String()];
                }
            });
        return $paths;
    }
}
