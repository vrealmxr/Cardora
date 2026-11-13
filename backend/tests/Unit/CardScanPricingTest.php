<?php

namespace Tests\Unit;

use App\Services\CardScanService;
use App\Services\EbayPriceService;
use RuntimeException;
use Tests\TestCase;

class CardScanPricingTest extends TestCase
{
    private function prediction(array $identity, array $extra = []): array
    {
        return ['identity' => $identity + ['name' => null, 'set_name' => null, 'collector_number' => null, 'language' => null], 'outcome' => 'completed'] + $extra;
    }

    public function test_tcg_query_is_name_and_collector_number(): void
    {
        $query = app(CardScanService::class)->priceQuery(
            $this->prediction(['name' => 'Charizard ex', 'set_name' => 'Scarlet & Violet—151', 'collector_number' => '199/165']),
            'pokemon'
        );

        $this->assertSame('Charizard ex 199/165', $query);
    }

    public function test_sports_query_adds_the_set_without_generic_words_and_the_parallel(): void
    {
        $service = app(CardScanService::class);
        $identity = ['name' => 'Stephen Curry', 'set_name' => 'Basketball Cards 2025 Topps Chrome', 'collector_number' => '201'];

        $this->assertSame('Stephen Curry 2025 Topps Chrome 201', $service->priceQuery($this->prediction($identity, ['parallel' => null]), 'nba'));
        $this->assertSame('Stephen Curry 2025 Topps Chrome 201 Gold Refractor', $service->priceQuery($this->prediction($identity, ['parallel' => 'Gold Refractor']), 'nba'));
        $this->assertSame('Stephen Curry 2025 Topps Chrome 201', $service->priceQuery($this->prediction($identity, ['parallel' => 'Base']), 'nba'));
    }

    public function test_tcg_without_number_falls_back_to_the_set(): void
    {
        $query = app(CardScanService::class)->priceQuery($this->prediction(['name' => 'Elsa', 'set_name' => 'The First Chapter']), 'lorcana');

        $this->assertSame('Elsa The First Chapter', $query);
    }

    public function test_no_query_without_a_name(): void
    {
        $this->assertNull(app(CardScanService::class)->priceQuery($this->prediction([]), 'lorcana'));
    }

    public function test_market_price_is_trimmed_from_the_ebay_estimate(): void
    {
        $this->app->instance(EbayPriceService::class, new class extends EbayPriceService {
            public array $queries = [];

            public function isConfigured(): bool
            {
                return true;
            }

            public function estimate(string $query, array $options = []): array
            {
                $this->queries[] = $query;

                return [
                    'currency' => 'EUR', 'median' => 326.23, 'condition_filter' => 'ungraded', 'used_for_stats' => 129,
                    'total_matches' => 1516, 'price_basis' => 'active_listings', 'fetched_at' => '2026-11-13T15:40:00+00:00',
                    'estimated_sale' => ['price' => 268.92, 'basis' => 'us_p30', 'sample_size' => 23, 'confidence' => 'high'],
                    'items' => array_fill(0, 8, ['title' => 'Charizard ex', 'price' => 270.0, 'currency' => 'EUR', 'url' => 'https://www.ebay.com/itm/1',
                        'image_url' => null, 'marketplace_id' => 'EBAY_US', 'condition' => 'Ungraded', 'original_price' => 300, 'graded' => false]),
                ];
            }
        });

        $price = app(CardScanService::class)->marketPrice($this->prediction(['name' => 'Charizard ex', 'collector_number' => '199/165']), 'pokemon');

        $this->assertSame(['Charizard ex 199/165'], app(EbayPriceService::class)->queries);
        $this->assertSame(268.92, $price['estimated_sale']['price']);
        $this->assertSame(326.23, $price['median_asking']);
        $this->assertSame('Charizard ex 199/165', $price['query']);
        $this->assertSame('exact', $price['match']);
        $this->assertCount(5, $price['items']);
        $this->assertArrayNotHasKey('original_price', $price['items'][0]);
    }

    public function test_search_with_a_bare_number_that_finds_nothing_is_retried_without_it(): void
    {
        $this->app->instance(EbayPriceService::class, new class extends EbayPriceService {
            public array $queries = [];

            public function isConfigured(): bool
            {
                return true;
            }

            public function estimate(string $query, array $options = []): array
            {
                $this->queries[] = $query;
                $found = str_contains($query, '42') ? 0 : 38;

                return ['query' => $query, 'currency' => 'EUR', 'median' => $found ? 12.0 : null, 'condition_filter' => 'ungraded',
                    'used_for_stats' => $found, 'total_matches' => $found, 'price_basis' => 'active_listings', 'fetched_at' => 'now',
                    'estimated_sale' => $found ? ['price' => 6.0, 'basis' => 'us_p30', 'sample_size' => $found, 'confidence' => 'medium'] : null,
                    'items' => []];
            }
        });

        $price = app(CardScanService::class)->marketPrice(
            $this->prediction(['name' => 'Elsa - Spirit of Winter', 'set_name' => 'The First Chapter', 'collector_number' => '42']),
            'lorcana'
        );

        $this->assertSame(['Elsa - Spirit of Winter 42', 'Elsa - Spirit of Winter The First Chapter'], app(EbayPriceService::class)->queries);
        $this->assertSame('Elsa - Spirit of Winter The First Chapter', $price['query']);
        $this->assertSame('without_number', $price['match']);
        $this->assertSame(6.0, $price['estimated_sale']['price']);
    }

    public function test_name_alone_is_the_last_resort(): void
    {
        $this->app->instance(EbayPriceService::class, new class extends EbayPriceService {
            public array $queries = [];

            public function isConfigured(): bool
            {
                return true;
            }

            public function estimate(string $query, array $options = []): array
            {
                $this->queries[] = $query;
                $found = $query === 'Elsa - Spirit of Winter' ? 40 : 1;

                return ['currency' => 'EUR', 'median' => 9.0, 'condition_filter' => 'ungraded', 'used_for_stats' => $found,
                    'total_matches' => $found, 'price_basis' => 'active_listings', 'fetched_at' => 'now',
                    'estimated_sale' => ['price' => 5.0, 'basis' => 'us_p30', 'sample_size' => $found, 'confidence' => 'medium'], 'items' => []];
            }
        });

        $price = app(CardScanService::class)->marketPrice(
            $this->prediction(['name' => 'Elsa - Spirit of Winter', 'set_name' => 'The First Chapter', 'collector_number' => '42']),
            'lorcana'
        );

        $this->assertCount(3, app(EbayPriceService::class)->queries);
        $this->assertSame('name_only', $price['match']);
        $this->assertSame(40, $price['used_for_stats']);
    }

    public function test_market_price_never_throws(): void
    {
        $this->app->instance(EbayPriceService::class, new class extends EbayPriceService {
            public function isConfigured(): bool
            {
                return true;
            }

            public function estimate(string $query, array $options = []): array
            {
                throw new RuntimeException('eBay down');
            }
        });

        $this->assertNull(app(CardScanService::class)->marketPrice($this->prediction(['name' => 'Pikachu', 'collector_number' => '25']), 'pokemon'));
    }

    public function test_market_price_is_skipped_when_ebay_is_not_configured(): void
    {
        config(['services.ebay.enabled' => false]);

        $this->assertNull(app(CardScanService::class)->marketPrice($this->prediction(['name' => 'Pikachu', 'collector_number' => '25']), 'pokemon'));
    }
}
