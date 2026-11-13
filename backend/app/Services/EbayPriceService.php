<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Market price estimates from eBay's Browse API (active listings).
 *
 * Uses an application token (client-credentials grant), so no eBay user
 * account is linked and no eBay user data is stored. Prices are asking
 * prices of live listings, not sold prices — sold data needs the
 * restricted Marketplace Insights API.
 */
class EbayPriceService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.ebay.enabled')
            && filled(config('services.ebay.client_id'))
            && filled(config('services.ebay.client_secret'));
    }

    public function environment(): string
    {
        return config('services.ebay.environment') === 'production' ? 'production' : 'sandbox';
    }

    public function host(): string
    {
        return config('services.ebay.hosts.'.$this->environment());
    }

    /**
     * Search live listings and return a price summary.
     *
     * @param  array{category_ids?: string|null, limit?: int, marketplace_id?: string, fresh?: bool}  $options
     */
    public function estimate(string $query, array $options = []): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        if ($query === '') {
            throw new RuntimeException('eBay price search needs a query.');
        }

        $marketplace = $options['marketplace_id'] ?? config('services.ebay.marketplace_id');
        $limit = max(1, min(200, (int) ($options['limit'] ?? config('services.ebay.search_limit', 50))));
        $category = $this->resolveCategory($query, $marketplace, $options);

        $cacheKey = 'ebay:price:'.sha1(implode('|', [$this->environment(), $marketplace, $category['id'] ?? '', $limit, mb_strtolower($query)]));

        if (! empty($options['fresh'])) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('services.ebay.cache_minutes', 360)),
            fn () => $this->summarize($query, $marketplace, $category, $this->search($query, $marketplace, $category['id'], $limit))
        );
    }

    /**
     * Pick the one eBay category to search in (Browse accepts at most one).
     *
     * - category_ids given with one id: use it as-is.
     * - category_ids given with several ids: the query's best Taxonomy
     *   suggestion among them, otherwise the first one.
     * - category_ids given but empty: no category filter.
     * - no category_ids: eBay's top Taxonomy suggestion for the query, so a
     *   Pokémon card lands in TCG singles and a LeBron rookie in sports
     *   singles. If eBay suggests nothing (or the lookup fails) the search
     *   runs without a category filter instead of failing.
     *
     * @return array{id: ?string, name: ?string, source: string}
     */
    public function resolveCategory(string $query, string $marketplace, array $options = []): array
    {
        $none = ['id' => null, 'name' => null, 'source' => 'none'];
        $fresh = ! empty($options['fresh']);

        if (array_key_exists('category_ids', $options)) {
            $requested = array_values(array_filter(array_map('trim', explode(',', (string) $options['category_ids'])), 'strlen'));

            if ($requested === []) {
                return $none;
            }

            if (count($requested) > 1) {
                foreach ($this->suggestCategories($query, $marketplace, $fresh) as $suggestion) {
                    if (in_array($suggestion['id'], $requested, true)) {
                        return ['source' => 'request'] + $suggestion;
                    }
                }
            }

            return ['id' => $requested[0], 'name' => null, 'source' => 'request'];
        }

        return $this->suggestCategories($query, $marketplace, $fresh)[0] ?? $none;
    }

    /**
     * Leaf categories eBay's Taxonomy API suggests for a query, best first.
     * Successful lookups are cached; failures are logged and not cached.
     *
     * @return list<array{id: string, name: ?string, source: string}>
     */
    public function suggestCategories(string $query, string $marketplace, bool $fresh = false): array
    {
        $cacheKey = 'ebay:category:'.sha1(implode('|', [$this->environment(), $marketplace, mb_strtolower($query)]));

        if (! $fresh && is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        try {
            $treeId = $this->categoryTreeId($marketplace);
            $payload = $this->request($marketplace)
                ->get("/commerce/taxonomy/v1/category_tree/{$treeId}/get_category_suggestions", ['q' => mb_substr($query, 0, 350)])
                ->throw()
                ->json() ?? [];
        } catch (RequestException|RuntimeException $exception) {
            if ($exception instanceof RequestException && $exception->response?->status() === 401) {
                Cache::forget($this->tokenCacheKey());
            }

            Log::warning('eBay category suggestion failed; searching without a category', [
                'query' => $query,
                'marketplace' => $marketplace,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            return [];
        }

        $suggestions = [];

        foreach ($payload['categorySuggestions'] ?? [] as $suggestion) {
            $id = (string) ($suggestion['category']['categoryId'] ?? '');

            if ($id !== '') {
                $suggestions[] = ['id' => $id, 'name' => $suggestion['category']['categoryName'] ?? null, 'source' => 'taxonomy'];
            }
        }

        Cache::put($cacheKey, $suggestions, now()->addMinutes((int) config('services.ebay.category_cache_minutes', 10080)));

        return $suggestions;
    }

    private function categoryTreeId(string $marketplace): string
    {
        return Cache::remember('ebay:category-tree:'.$this->environment().':'.$marketplace, now()->addDays(30), function () use ($marketplace) {
            $treeId = (string) ($this->request($marketplace)
                ->get('/commerce/taxonomy/v1/get_default_category_tree_id', ['marketplace_id' => $marketplace])
                ->throw()
                ->json('categoryTreeId') ?? '');

            if ($treeId === '') {
                throw new RuntimeException("eBay returned no category tree for {$marketplace}.");
            }

            return $treeId;
        });
    }

    public function search(string $query, string $marketplace, ?string $categoryId, int $limit): array
    {
        $params = array_filter([
            'q' => $query,
            'limit' => $limit,
            'category_ids' => filled($categoryId) ? $categoryId : null,
            'filter' => 'buyingOptions:{FIXED_PRICE|AUCTION}',
        ], fn ($value) => $value !== null);

        try {
            return $this->request($marketplace)
                ->get('/buy/browse/v1/item_summary/search', $params)
                ->throw()
                ->json() ?? [];
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 401) {
                Cache::forget($this->tokenCacheKey());
            }

            throw new RuntimeException(sprintf(
                'eBay Browse search failed with status %s: %s',
                $exception->response?->status() ?? 'unknown',
                $exception->response?->body() ?: $exception->getMessage()
            ), previous: $exception);
        }
    }

    public function accessToken(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('eBay API is not configured (EBAY_API_ENABLED / EBAY_CLIENT_ID / EBAY_CLIENT_SECRET).');
        }

        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth(config('services.ebay.client_id'), config('services.ebay.client_secret'))
                ->acceptJson()
                ->timeout(15)
                ->post($this->host().'/identity/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'scope' => config('services.ebay.scope'),
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(sprintf(
                'eBay OAuth token request failed with status %s: %s',
                $exception->response?->status() ?? 'unknown',
                $exception->response?->body() ?: $exception->getMessage()
            ), previous: $exception);
        }

        $token = (string) ($response['access_token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('eBay OAuth response did not include an access_token.');
        }

        $ttl = max(60, (int) ($response['expires_in'] ?? 7200) - 120);
        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds($ttl));

        return $token;
    }

    private function request(string $marketplace): PendingRequest
    {
        return Http::baseUrl($this->host())
            ->withToken($this->accessToken())
            ->withHeaders([
                'X-EBAY-C-MARKETPLACE-ID' => $marketplace,
                'Accept-Language' => 'en-US',
            ])
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }

    private function tokenCacheKey(): string
    {
        return 'ebay:app-token:'.$this->environment().':'.sha1((string) config('services.ebay.client_id'));
    }

    /**
     * Reduce raw item summaries to price stats. Outliers (lots, misprices,
     * graded slabs mixed into raw searches) are trimmed with Tukey fences
     * before the average and median are taken.
     */
    private function summarize(string $query, string $marketplace, array $category, array $payload): array
    {
        $currency = config('services.ebay.currency', 'EUR');
        $items = [];

        foreach ($payload['itemSummaries'] ?? [] as $summary) {
            $value = $summary['price']['value'] ?? null;

            if (! is_numeric($value) || (float) $value <= 0) {
                continue;
            }

            $items[] = [
                'item_id' => $summary['itemId'] ?? null,
                'title' => $summary['title'] ?? null,
                'price' => round((float) $value, 2),
                'currency' => $summary['price']['currency'] ?? $currency,
                'condition' => $summary['condition'] ?? null,
                'buying_options' => $summary['buyingOptions'] ?? [],
                'image_url' => $summary['image']['imageUrl'] ?? null,
                'url' => $summary['itemWebUrl'] ?? null,
                'item_location_country' => $summary['itemLocation']['country'] ?? null,
            ];
        }

        // Keep only listings in the dominant currency so stats are comparable.
        $currencyCounts = array_count_values(array_column($items, 'currency'));
        arsort($currencyCounts);
        $mainCurrency = array_key_first($currencyCounts) ?? $currency;
        $items = array_values(array_filter($items, fn ($item) => $item['currency'] === $mainCurrency));

        $prices = array_column($items, 'price');
        sort($prices);
        [$low, $high] = $this->outlierFences($prices);
        $trimmed = array_values(array_filter($prices, fn ($price) => $price >= $low && $price <= $high));

        // Sample listings shown to users: only the ones that fed the stats,
        // closest to the median first.
        $median = $this->median($trimmed);
        $items = array_values(array_filter($items, fn ($item) => $item['price'] >= $low && $item['price'] <= $high));
        usort($items, fn ($a, $b) => abs($a['price'] - $median) <=> abs($b['price'] - $median));

        return [
            'source' => 'ebay',
            'environment' => $this->environment(),
            'marketplace_id' => $marketplace,
            'query' => $query,
            'category' => $category,
            'currency' => $mainCurrency,
            'total_matches' => (int) ($payload['total'] ?? count($items)),
            'sample_size' => count($prices),
            'used_for_stats' => count($trimmed),
            'min' => $trimmed ? min($trimmed) : null,
            'max' => $trimmed ? max($trimmed) : null,
            'median' => $median,
            'average' => $trimmed ? round(array_sum($trimmed) / count($trimmed), 2) : null,
            'price_basis' => 'active_listings',
            'fetched_at' => now()->toIso8601String(),
            'items' => array_slice($items, 0, 10),
        ];
    }

    /**
     * @return array{0: float, 1: float} [low, high] Tukey fences
     */
    private function outlierFences(array $sorted): array
    {
        if (count($sorted) < 4) {
            return [-INF, INF];
        }

        $q1 = $this->percentile($sorted, 0.25);
        $q3 = $this->percentile($sorted, 0.75);
        $iqr = $q3 - $q1;

        return [$q1 - 1.5 * $iqr, $q3 + 1.5 * $iqr];
    }

    private function percentile(array $sorted, float $p): float
    {
        $index = ($count = count($sorted)) > 1 ? $p * ($count - 1) : 0;
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($index - $lower);
    }

    private function median(array $sorted): ?float
    {
        return $sorted === [] ? null : round($this->percentile($sorted, 0.5), 2);
    }
}
