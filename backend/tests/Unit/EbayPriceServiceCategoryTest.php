<?php

namespace Tests\Unit;

use App\Services\EbayPriceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EbayPriceServiceCategoryTest extends TestCase
{
    /** Browse searches seen by the fake: [marketplace, seller country, category]. */
    private array $searches = [];

    /** Suggestions the fake Taxonomy API returns, or an HTTP error status. */
    private array|int $suggestions = [];

    /** Listings per marketplace header: [legacyItemId, price, currency, conditionId?, title?]; an int is an HTTP error. */
    private array $listings = [];

    /** HTTP status the fake Browse API returns when a category filter is sent (null = accept). */
    private ?int $rejectCategoryFor = null;

    private bool $ecbUp = true;

    private array $searchedQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ebay.enabled' => true,
            'services.ebay.environment' => 'production',
            'services.ebay.client_id' => 'test-client',
            'services.ebay.client_secret' => 'test-secret',
            'services.ebay.currency' => 'EUR',
            'services.ebay.category_marketplace_id' => 'EBAY_US',
            'services.ebay.markets' => 'EBAY_DE:DE',
        ]);
        Cache::flush();
        $this->searches = [];
        $this->listings = ['EBAY_DE' => [['1', '10.00', 'EUR'], ['2', '12.00', 'EUR']]];

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'ecb.europa.eu')) {
                return $this->ecbUp
                    ? Http::response("<Cube time='2026-11-13'><Cube currency='USD' rate='1.0000'/><Cube currency='GBP' rate='0.5000'/>"
                        .str_repeat("<Cube currency='XXX' rate='1'/>", 10).'</Cube>')
                    : Http::response('down', 503);
            }

            if (str_contains($url, '/identity/v1/oauth2/token')) {
                return Http::response(['access_token' => 'token', 'expires_in' => 7200]);
            }

            if (str_contains($url, 'get_default_category_tree_id')) {
                return Http::response(['categoryTreeId' => '0']);
            }

            if (str_contains($url, 'get_category_suggestions')) {
                return is_int($this->suggestions)
                    ? Http::response(['errors' => [['message' => 'boom']]], $this->suggestions)
                    : Http::response(['categorySuggestions' => array_map(
                        fn ($s) => ['category' => ['categoryId' => $s[0], 'categoryName' => $s[1]]],
                        $this->suggestions
                    )]);
            }

            if (str_contains($url, '/buy/browse/v1/item_summary/search')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $marketplace = $request->header('X-EBAY-C-MARKETPLACE-ID')[0] ?? '?';
                preg_match('/itemLocationCountry:([A-Z]{2})/', $query['filter'] ?? '', $country);
                $this->searches[] = [$marketplace, $country[1] ?? null, $query['category_ids'] ?? null];
                $this->searchedQueries[] = $query['q'] ?? null;

                if ($this->rejectCategoryFor !== null && isset($query['category_ids']) && $marketplace === 'EBAY_GB') {
                    return Http::response(['errors' => [['message' => 'bad category']]], $this->rejectCategoryFor);
                }

                $listings = $this->listings[$marketplace] ?? [];

                if (is_int($listings)) {
                    return Http::response(['errors' => [['message' => 'down']]], $listings);
                }

                return Http::response(['total' => count($listings), 'itemSummaries' => array_map(fn ($l) => [
                    'itemId' => 'v1|'.$l[0].'|0', 'legacyItemId' => $l[0], 'title' => $l[4] ?? 'Card '.$l[0],
                    'price' => ['value' => $l[1], 'currency' => $l[2]], 'conditionId' => $l[3] ?? '4000',
                ], $listings)]);
            }

            return Http::response([], 404);
        });
    }

    public function test_category_comes_from_the_query_via_taxonomy_suggestions(): void
    {
        $this->suggestions = [['261328', 'Trading Card Singles'], ['62124', 'Jerseys']];

        $result = app(EbayPriceService::class)->estimate('LeBron James Prizm rookie');

        $this->assertSame([['EBAY_DE', 'DE', '261328']], $this->searches);
        $this->assertSame(['id' => '261328', 'name' => 'Trading Card Singles', 'source' => 'taxonomy'], $result['category']);
        $this->assertSame(11.0, $result['median']);
    }

    public function test_several_requested_categories_resolve_to_one_matching_the_query(): void
    {
        $this->suggestions = [['183454', 'CCG Individual Cards'], ['261328', 'Trading Card Singles']];

        $result = app(EbayPriceService::class)->estimate('Charizard ex 199/165', ['category_ids' => '261328,183454']);

        $this->assertSame('183454', $this->searches[0][2]);
        $this->assertSame('request', $result['category']['source']);
    }

    public function test_single_or_empty_requested_category_skips_suggestions(): void
    {
        $ebay = app(EbayPriceService::class);

        $ebay->estimate('Pikachu', ['category_ids' => '2536']);
        $ebay->estimate('Pikachu', ['category_ids' => '']);

        $this->assertSame(['2536', null], array_column($this->searches, 2));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'get_category_suggestions'));
    }

    public function test_failed_suggestion_lookup_searches_without_category_and_is_not_cached(): void
    {
        $this->suggestions = 500;
        $ebay = app(EbayPriceService::class);

        $result = $ebay->estimate('Charizard ex 199/165');

        $this->assertSame([null], array_column($this->searches, 2));
        $this->assertSame('none', $result['category']['source']);
        $this->assertSame(2, $result['sample_size']);

        $this->suggestions = [['183454', 'CCG Individual Cards']];
        $this->assertSame('183454', $ebay->suggestCategories('Charizard ex 199/165', 'EBAY_US')[0]['id']);
    }

    public function test_all_markets_are_searched_for_local_sellers_and_merged_in_eur(): void
    {
        config(['services.ebay.markets' => 'EBAY_US:US,EBAY_GB:GB,EBAY_DE:DE,EBAY_DE:GR']);
        $this->suggestions = [['183454', 'CCG Individual Cards']];
        $this->listings = [
            'EBAY_US' => [['10', '20.00', 'USD'], ['11', '22.00', 'USD']],   // 1 EUR = 1 USD in the fake
            'EBAY_GB' => [['20', '5.00', 'GBP'], ['10', '20.00', 'USD']],    // 5 GBP = 10 EUR; item 10 is a duplicate
            'EBAY_DE' => [['30', '15.00', 'EUR'], ['31', '9.99', 'JPY']],    // JPY has no rate in the fake
        ];

        $result = app(EbayPriceService::class)->estimate('Charizard ex 199/165');

        $this->assertEqualsCanonicalizing([
            ['EBAY_US', 'US', '183454'], ['EBAY_GB', 'GB', '183454'], ['EBAY_DE', 'DE', '183454'], ['EBAY_DE', 'GR', '183454'],
        ], $this->searches);
        // US 20, 22 + GB 10 + DE 15 (twice: DE and GR markets get the same fake listings, de-duplicated)
        $this->assertSame([10.0, 15.0, 20.0, 22.0], collect($result['items'])->pluck('price')->sort()->values()->all());
        $this->assertSame('EUR', $result['currency']);
        $this->assertSame(17.5, $result['median']);
        $this->assertSame(['source' => 'ECB', 'date' => '2026-11-13'], $result['exchange_rates']);

        $gb = collect($result['items'])->firstWhere('original_currency', 'GBP');
        $this->assertSame(['price' => 10.0, 'original_price' => 5.0, 'marketplace_id' => 'EBAY_GB'], array_intersect_key($gb, array_flip(['price', 'original_price', 'marketplace_id'])));

        $markets = collect($result['marketplaces'])->keyBy(fn ($m) => $m['marketplace_id'].':'.$m['seller_country']);
        $this->assertSame(2, $markets['EBAY_US:US']['sample_size']);
        $this->assertSame(1, $markets['EBAY_GB:GB']['sample_size']);
        $this->assertSame('GBP', $markets['EBAY_GB:GB']['local_currency']);
        $this->assertSame(0, $markets['EBAY_DE:GR']['sample_size']);
    }

    public function test_a_failing_market_is_reported_without_failing_the_lookup(): void
    {
        config(['services.ebay.markets' => 'EBAY_US:US,EBAY_GB:GB']);
        $this->listings = ['EBAY_US' => [['10', '20.00', 'USD']], 'EBAY_GB' => 503];

        $result = app(EbayPriceService::class)->estimate('Charizard', ['category_ids' => '']);

        $markets = collect($result['marketplaces'])->keyBy('marketplace_id');
        $this->assertNull($markets['EBAY_US']['error']);
        $this->assertStringStartsWith('HTTP 503', $markets['EBAY_GB']['error']);
        $this->assertSame(20.0, $result['median']);
    }

    public function test_every_market_failing_throws(): void
    {
        $this->listings = ['EBAY_DE' => 503];

        $this->expectException(\RuntimeException::class);
        app(EbayPriceService::class)->estimate('Charizard', ['category_ids' => '']);
    }

    public function test_market_rejecting_the_category_is_retried_without_it(): void
    {
        config(['services.ebay.markets' => 'EBAY_US:US,EBAY_GB:GB']);
        $this->rejectCategoryFor = 400;
        $this->listings = ['EBAY_US' => [['10', '20.00', 'USD']], 'EBAY_GB' => [['20', '5.00', 'GBP']]];

        $result = app(EbayPriceService::class)->estimate('Charizard', ['category_ids' => '183454']);

        $this->assertContains(['EBAY_GB', 'GB', null], $this->searches);
        $gb = collect($result['marketplaces'])->firstWhere('marketplace_id', 'EBAY_GB');
        $this->assertTrue($gb['category_dropped']);
        $this->assertNull($gb['error']);
        $this->assertSame(2, $result['sample_size']);
    }

    public function test_without_exchange_rates_only_eur_listings_count(): void
    {
        config(['services.ebay.markets' => 'EBAY_US:US,EBAY_DE:DE']);
        $this->ecbUp = false;
        $this->listings = ['EBAY_US' => [['10', '20.00', 'USD']], 'EBAY_DE' => [['30', '15.00', 'EUR']]];

        $result = app(EbayPriceService::class)->estimate('Charizard', ['category_ids' => '']);

        $this->assertSame(1, $result['sample_size']);
        $this->assertSame(15.0, $result['median']);
        $this->assertNull($result['exchange_rates']['date']);
    }

    public function test_graded_listings_are_left_out_of_a_raw_card_search(): void
    {
        $this->listings = ['EBAY_DE' => [
            ['1', '10.00', 'EUR'], ['2', '12.00', 'EUR'],
            ['3', '90.00', 'EUR', '2750'],                       // graded by condition
            ['4', '80.00', 'EUR', '4000', 'Charizard PSA 10'],   // graded by title
        ]];

        $result = app(EbayPriceService::class)->estimate('Charizard', ['category_ids' => '']);

        $this->assertSame('ungraded', $result['condition_filter']);
        $this->assertSame(2, $result['excluded_listings']);
        $this->assertSame(11.0, $result['median']);
        $this->assertFalse(collect($result['items'])->contains('graded', true));
    }

    public function test_a_graded_search_is_priced_on_graded_listings(): void
    {
        $this->listings = ['EBAY_DE' => [['1', '10.00', 'EUR'], ['3', '90.00', 'EUR', '2750'], ['4', '80.00', 'EUR', '4000', 'Charizard PSA 10']]];

        $result = app(EbayPriceService::class)->estimate('Charizard PSA 10', ['category_ids' => '']);

        $this->assertSame('graded', $result['condition_filter']);
        $this->assertSame(85.0, $result['median']);
    }

    public function test_tag_team_and_ace_spec_titles_are_not_mistaken_for_graded(): void
    {
        $this->listings = ['EBAY_DE' => [['1', '10.00', 'EUR', '4000', 'Pikachu & Zekrom GX Tag Team'], ['2', '12.00', 'EUR', '4000', 'Prime Catcher ACE SPEC']]];

        $result = app(EbayPriceService::class)->estimate('Pikachu', ['category_ids' => '']);

        $this->assertSame(0, $result['excluded_listings']);
    }

    public function test_sale_estimate_is_the_30th_percentile_of_us_listings_when_the_us_has_enough(): void
    {
        config(['services.ebay.markets' => 'EBAY_US:US,EBAY_DE:DE']);
        // US: 10 listings at 100..190 USD (1 USD = 1 EUR in the fake); DE asks much more.
        $this->listings = [
            'EBAY_US' => array_map(fn ($i) => [(string) (100 + $i), (string) (100 + 10 * $i), 'USD'], range(0, 9)),
            'EBAY_DE' => [['900', '400.00', 'EUR'], ['901', '420.00', 'EUR']],
        ];

        $result = app(EbayPriceService::class)->estimate('Charizard', ['category_ids' => '']);

        // p30 of 100..190 = 127
        $this->assertSame(['price' => 127.0, 'basis' => 'us_p30', 'sample_size' => 10, 'confidence' => 'medium'], $result['estimated_sale']);
        // Listings shown first are the ones closest to the estimate.
        $this->assertSame(130.0, $result['items'][0]['price']);
    }

    public function test_sale_estimate_falls_back_to_all_markets_and_flags_cheap_cards(): void
    {
        config(['services.ebay.markets' => 'EBAY_US:US,EBAY_DE:DE']);
        $this->listings = [
            'EBAY_US' => [['1', '2.00', 'USD'], ['2', '3.00', 'USD']],
            'EBAY_DE' => [['3', '4.00', 'EUR'], ['4', '5.00', 'EUR'], ['5', '6.00', 'EUR']],
        ];

        $estimate = app(EbayPriceService::class)->estimate('Common card', ['category_ids' => ''])['estimated_sale'];

        $this->assertSame('all_markets_p25', $estimate['basis']);
        $this->assertSame(3.0, $estimate['price']);
        $this->assertSame('low', $estimate['confidence']);
    }

    public function test_no_sale_estimate_with_too_few_listings(): void
    {
        $this->listings = ['EBAY_DE' => [['1', '10.00', 'EUR'], ['2', '12.00', 'EUR']]];

        $this->assertNull(app(EbayPriceService::class)->estimate('Rare thing', ['category_ids' => ''])['estimated_sale']);
    }

    public function test_dashes_that_would_exclude_words_are_removed_but_codes_are_kept(): void
    {
        app(EbayPriceService::class)->estimate('Elsa - Spirit of Winter -foil OP01-120', ['category_ids' => '']);

        $this->assertSame(['Elsa Spirit of Winter foil OP01-120'], $this->searchedQueries);
    }

    public function test_us_listings_priced_above_the_overall_median_are_not_used(): void
    {
        config(['services.ebay.markets' => 'EBAY_US:US,EBAY_DE:DE']);
        // US: 8 pricey parallels; everywhere else: 20 cheap base copies.
        $this->listings = [
            'EBAY_US' => array_map(fn ($i) => [(string) (100 + $i), (string) (40 + $i), 'USD'], range(0, 7)),
            'EBAY_DE' => array_map(fn ($i) => [(string) (200 + $i), (string) (3 + $i * 0.1), 'EUR'], range(0, 19)),
        ];

        $estimate = app(EbayPriceService::class)->estimate('Stephen Curry 2025 Topps Chrome 201', ['category_ids' => ''])['estimated_sale'];

        $this->assertSame('all_markets_p25', $estimate['basis']);
        $this->assertLessThan(10, $estimate['price']);
        $this->assertNotSame('high', $estimate['confidence']);
    }

    public function test_markets_spec_is_parsed_and_deduplicated(): void
    {
        $markets = app(EbayPriceService::class)->markets('EBAY_US:US, ebay_it ,EBAY_DE:GR,EBAY_US:US,bogus');

        $this->assertSame([
            ['marketplace' => 'EBAY_US', 'country' => 'US'],
            ['marketplace' => 'EBAY_IT', 'country' => 'IT'],
            ['marketplace' => 'EBAY_DE', 'country' => 'GR'],
        ], $markets);
    }
}
