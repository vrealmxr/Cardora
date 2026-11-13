<?php

namespace Tests\Unit;

use App\Services\EbayPriceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EbayPriceServiceCategoryTest extends TestCase
{
    private array $searchedCategories = [];

    /** Suggestions the fake Taxonomy API returns, or an HTTP error status. */
    private array|int $suggestions = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ebay.enabled' => true,
            'services.ebay.environment' => 'production',
            'services.ebay.client_id' => 'test-client',
            'services.ebay.client_secret' => 'test-secret',
            'services.ebay.marketplace_id' => 'EBAY_DE',
        ]);
        Cache::flush();
        $this->searchedCategories = [];
    }

    private function fakeEbay(array|int $suggestions): void
    {
        $this->suggestions = $suggestions;

        Http::fake(function (Request $request) {
            $url = $request->url();
            $suggestions = $this->suggestions;

            if (str_contains($url, '/identity/v1/oauth2/token')) {
                return Http::response(['access_token' => 'token', 'expires_in' => 7200]);
            }

            if (str_contains($url, 'get_default_category_tree_id')) {
                return Http::response(['categoryTreeId' => '77']);
            }

            if (str_contains($url, 'get_category_suggestions')) {
                return is_int($suggestions)
                    ? Http::response(['errors' => [['message' => 'boom']]], $suggestions)
                    : Http::response(['categorySuggestions' => array_map(
                        fn ($s) => ['category' => ['categoryId' => $s[0], 'categoryName' => $s[1]]],
                        $suggestions
                    )]);
            }

            if (str_contains($url, '/buy/browse/v1/item_summary/search')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $this->searchedCategories[] = $query['category_ids'] ?? null;

                return Http::response(['total' => 2, 'itemSummaries' => [
                    ['itemId' => '1', 'title' => 'A', 'price' => ['value' => '10.00', 'currency' => 'EUR']],
                    ['itemId' => '2', 'title' => 'B', 'price' => ['value' => '12.00', 'currency' => 'EUR']],
                ]]);
            }

            return Http::response([], 404);
        });
    }

    public function test_category_comes_from_the_query_via_taxonomy_suggestions(): void
    {
        $this->fakeEbay([['261328', 'Trading Card Einzelkarten'], ['62124', 'Trikots']]);

        $result = app(EbayPriceService::class)->estimate('LeBron James Prizm rookie');

        $this->assertSame(['261328'], $this->searchedCategories);
        $this->assertSame(['id' => '261328', 'name' => 'Trading Card Einzelkarten', 'source' => 'taxonomy'], $result['category']);
        $this->assertSame(11.0, $result['median']);
    }

    public function test_several_requested_categories_resolve_to_one_matching_the_query(): void
    {
        $this->fakeEbay([['183454', 'TCG Einzelkarten'], ['261328', 'Trading Card Einzelkarten']]);

        $result = app(EbayPriceService::class)->estimate('Charizard ex 199/165', ['category_ids' => '261328,183454']);

        $this->assertSame(['183454'], $this->searchedCategories);
        $this->assertSame('request', $result['category']['source']);
    }

    public function test_single_or_empty_requested_category_skips_suggestions(): void
    {
        $this->fakeEbay([['183454', 'TCG Einzelkarten']]);
        $ebay = app(EbayPriceService::class);

        $ebay->estimate('Pikachu', ['category_ids' => '2536']);
        $ebay->estimate('Pikachu', ['category_ids' => '']);

        $this->assertSame(['2536', null], $this->searchedCategories);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'get_category_suggestions'));
    }

    public function test_failed_suggestion_lookup_searches_without_category_and_is_not_cached(): void
    {
        $this->fakeEbay(500);
        $ebay = app(EbayPriceService::class);

        $result = $ebay->estimate('Charizard ex 199/165');

        $this->assertSame([null], $this->searchedCategories);
        $this->assertSame('none', $result['category']['source']);
        $this->assertSame(2, $result['sample_size']);

        $this->suggestions = [['183454', 'TCG Einzelkarten']];
        $this->assertSame('183454', $ebay->suggestCategories('Charizard ex 199/165', 'EBAY_DE')[0]['id']);
    }
}
