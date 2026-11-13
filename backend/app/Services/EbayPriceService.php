<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Market price estimates from eBay's Browse API (active listings).
 *
 * Searches every configured eBay marketplace in parallel (US, GB, DE, ...).
 * Each marketplace is restricted to sellers located in its own country, so
 * the same listing is never counted twice and prices come in the seller's
 * own currency; they are converted to EUR with ECB reference rates.
 *
 * Uses an application token (client-credentials grant), so no eBay user
 * account is linked and no eBay user data is stored. Prices are asking
 * prices of live listings, not sold prices — sold data needs the
 * restricted Marketplace Insights API.
 */
class EbayPriceService
{
    private const ECB_RATES_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

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
     * Search live listings on all configured marketplaces and return one
     * price summary in the target currency (EUR).
     *
     * @param  array{category_ids?: string|null, limit?: int, markets?: string, fresh?: bool}  $options
     */
    public function estimate(string $query, array $options = []): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        if ($query === '') {
            throw new RuntimeException('eBay price search needs a query.');
        }

        $markets = $this->markets($options['markets'] ?? null);
        $limit = max(1, min(200, (int) ($options['limit'] ?? config('services.ebay.search_limit', 50))));
        // Category ids are shared across eBay's sites, so one lookup on the
        // reference marketplace (best taxonomy) applies to all of them.
        $category = $this->resolveCategory($query, (string) config('services.ebay.category_marketplace_id', 'EBAY_US'), $options);

        $marketKey = implode(',', array_map(fn ($m) => $m['marketplace'].':'.$m['country'], $markets));
        $cacheKey = 'ebay:price:'.sha1(implode('|', [$this->environment(), $marketKey, $category['id'] ?? '', $limit, mb_strtolower($query)]));

        if (! empty($options['fresh'])) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('services.ebay.cache_minutes', 360)),
            fn () => $this->summarize($query, $category, $this->searchMarkets($query, $markets, $category['id'], $limit))
        );
    }

    /**
     * Configured markets as marketplace + seller country pairs, e.g.
     * "EBAY_US:US,EBAY_DE:DE,EBAY_DE:GR" (GR = Greek sellers seen via eBay.de).
     * A bare "EBAY_IT" means sellers in IT.
     *
     * @return list<array{marketplace: string, country: string}>
     */
    public function markets(?string $spec = null): array
    {
        $markets = [];

        foreach (explode(',', (string) ($spec ?? config('services.ebay.markets'))) as $entry) {
            if (! preg_match('/^\s*(EBAY_[A-Z]{2})(?::([A-Z]{2}))?\s*$/', strtoupper($entry), $match)) {
                continue;
            }

            $market = ['marketplace' => $match[1], 'country' => $match[2] ?? substr($match[1], 5)];
            $markets[$market['marketplace'].':'.$market['country']] = $market;
        }

        if ($markets === []) {
            throw new RuntimeException('No valid eBay markets configured (EBAY_MARKETS).');
        }

        return array_values($markets);
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

    /**
     * Run the Browse search on every market at once. A market whose request
     * is rejected with the category filter is retried once without it; a
     * market that still fails is reported in its result instead of failing
     * the whole lookup. Throws only when no market answered.
     *
     * @param  list<array{marketplace: string, country: string}>  $markets
     * @return list<array{marketplace: string, country: string, payload: ?array, error: ?string, category_dropped: bool}>
     */
    public function searchMarkets(string $query, array $markets, ?string $categoryId, int $limit): array
    {
        $token = $this->accessToken();
        $results = [];
        $pending = array_keys($markets);

        foreach ([$categoryId, null] as $attempt => $category) {
            if ($pending === [] || ($attempt === 1 && ! filled($categoryId))) {
                break;
            }

            $responses = Http::pool(function (Pool $pool) use ($pending, $markets, $token, $query, $limit, $category) {
                foreach ($pending as $index) {
                    $pool->as((string) $index)
                        ->baseUrl($this->host())
                        ->withToken($token)
                        ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => $markets[$index]['marketplace'], 'Accept-Language' => 'en-US'])
                        ->acceptJson()
                        ->timeout(20)
                        ->get('/buy/browse/v1/item_summary/search', array_filter([
                            'q' => $query,
                            'limit' => $limit,
                            'category_ids' => filled($category) ? $category : null,
                            'filter' => 'buyingOptions:{FIXED_PRICE|AUCTION},itemLocationCountry:'.$markets[$index]['country'],
                        ], fn ($value) => $value !== null));
                }
            });

            $retry = [];

            foreach ($pending as $index) {
                $response = $responses[(string) $index] ?? null;
                $result = $markets[$index] + ['payload' => null, 'error' => null, 'category_dropped' => $attempt === 1];

                if ($response instanceof Response && $response->successful()) {
                    $result['payload'] = $response->json() ?? [];
                } elseif ($response instanceof Response) {
                    if ($response->status() === 401) {
                        Cache::forget($this->tokenCacheKey());
                    }

                    if ($attempt === 0 && filled($categoryId) && $response->clientError() && $response->status() !== 401) {
                        $retry[] = $index;
                        continue;
                    }

                    $result['error'] = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                } else {
                    $result['error'] = $response instanceof Throwable ? mb_substr($response->getMessage(), 0, 300) : 'no response';
                }

                $results[$index] = $result;
            }

            $pending = $retry;
        }

        ksort($results);
        $results = array_values($results);
        $failed = array_filter($results, fn ($result) => $result['error'] !== null);

        foreach ($failed as $result) {
            Log::warning('eBay Browse search failed for one market', ['query' => $query, 'marketplace' => $result['marketplace'], 'country' => $result['country'], 'error' => $result['error']]);
        }

        if (count($failed) === count($results)) {
            throw new RuntimeException('eBay Browse search failed on every market: '.($results[0]['error'] ?? 'unknown error'));
        }

        return $results;
    }

    /**
     * EUR-based reference rates (1 EUR = rate × currency) from the ECB,
     * cached for 12 hours. If the ECB is unreachable the last good rates are
     * used; with none at all only EUR prices can be converted.
     *
     * @return array{date: ?string, rates: array<string, float>}
     */
    public function eurRates(): array
    {
        $cached = Cache::get('ebay:fx:ecb');

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $xml = Http::timeout(10)->get(self::ECB_RATES_URL)->throw()->body();
            preg_match_all("/currency='([A-Z]{3})'\\s+rate='([0-9.]+)'/", $xml, $matches, PREG_SET_ORDER);
            preg_match("/time='(\\d{4}-\\d{2}-\\d{2})'/", $xml, $date);

            if (count($matches) < 10) {
                throw new RuntimeException('Unexpected ECB rates response.');
            }

            $rates = ['EUR' => 1.0];
            foreach ($matches as [, $currency, $rate]) {
                $rates[$currency] = (float) $rate;
            }

            $fx = ['date' => $date[1] ?? null, 'rates' => $rates];
            Cache::put('ebay:fx:ecb', $fx, now()->addHours(12));
            Cache::forever('ebay:fx:ecb:last', $fx);

            return $fx;
        } catch (Throwable $exception) {
            Log::warning('ECB exchange rates unavailable', ['error' => mb_substr($exception->getMessage(), 0, 300)]);
            $last = Cache::get('ebay:fx:ecb:last');

            return is_array($last) ? $last : ['date' => null, 'rates' => ['EUR' => 1.0]];
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
     * Merge every market's listings into one set of price stats in the
     * target currency. Listings are de-duplicated by eBay item id, converted
     * with ECB rates (a listing in a currency without a rate is skipped),
     * and outliers (lots, misprices, graded slabs mixed into raw searches)
     * are trimmed with Tukey fences before the average and median are taken.
     *
     * @param  list<array{marketplace: string, country: string, payload: ?array, error: ?string, category_dropped: bool}>  $results
     */
    private function summarize(string $query, array $category, array $results): array
    {
        $target = strtoupper((string) config('services.ebay.currency', 'EUR'));
        $fx = $this->eurRates();
        $convert = function (float $value, string $currency) use ($fx, $target): ?float {
            $from = $fx['rates'][$currency] ?? null;
            $to = $fx['rates'][$target] ?? null;

            return $from && $to ? round($value / $from * $to, 2) : null;
        };

        $items = [];
        $markets = [];

        foreach ($results as $result) {
            $market = [
                'marketplace_id' => $result['marketplace'],
                'seller_country' => $result['country'],
                'total_matches' => (int) ($result['payload']['total'] ?? 0),
                'sample_size' => 0,
                'median' => null,
                'local_currency' => null,
                'category_dropped' => $result['category_dropped'],
                'error' => $result['error'],
            ];
            $marketPrices = [];
            $localCurrencies = [];

            foreach ($result['payload']['itemSummaries'] ?? [] as $summary) {
                $value = $summary['price']['value'] ?? null;
                $currency = strtoupper((string) ($summary['price']['currency'] ?? ''));
                $id = (string) ($summary['legacyItemId'] ?? $summary['itemId'] ?? '');
                $key = $id === '' ? 'anon:'.count($items) : 'id:'.$id;

                if (! is_numeric($value) || (float) $value <= 0 || $currency === '' || isset($items[$key])) {
                    continue;
                }

                $price = $convert((float) $value, $currency);
                $localCurrencies[] = $currency;

                if ($price === null) {
                    continue;
                }

                $item = [
                    'item_id' => $summary['itemId'] ?? null,
                    'title' => $summary['title'] ?? null,
                    'price' => $price,
                    'currency' => $target,
                    'original_price' => round((float) $value, 2),
                    'original_currency' => $currency,
                    'marketplace_id' => $result['marketplace'],
                    'condition' => $summary['condition'] ?? null,
                    'buying_options' => $summary['buyingOptions'] ?? [],
                    'image_url' => $summary['image']['imageUrl'] ?? null,
                    'url' => $summary['itemWebUrl'] ?? null,
                    'item_location_country' => $summary['itemLocation']['country'] ?? null,
                ];

                $items[$key] = $item;
                $marketPrices[] = $price;
            }

            sort($marketPrices);
            $market['sample_size'] = count($marketPrices);
            $market['median'] = $this->median($marketPrices);
            $counts = array_count_values($localCurrencies);
            arsort($counts);
            $market['local_currency'] = array_key_first($counts);
            $markets[] = $market;
        }

        $items = array_values($items);
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
            'query' => $query,
            'category' => $category,
            'currency' => $target,
            'total_matches' => array_sum(array_column($markets, 'total_matches')),
            'sample_size' => count($prices),
            'used_for_stats' => count($trimmed),
            'min' => $trimmed ? min($trimmed) : null,
            'max' => $trimmed ? max($trimmed) : null,
            'median' => $median,
            'average' => $trimmed ? round(array_sum($trimmed) / count($trimmed), 2) : null,
            'price_basis' => 'active_listings',
            'exchange_rates' => ['source' => 'ECB', 'date' => $fx['date']],
            'marketplaces' => $markets,
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
