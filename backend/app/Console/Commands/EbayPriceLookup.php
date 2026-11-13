<?php

namespace App\Console\Commands;

use App\Services\EbayPriceService;
use Illuminate\Console\Command;
use Throwable;

class EbayPriceLookup extends Command
{
    protected $signature = 'ebay:price
        {query : Card search, e.g. "Charizard ex 199/165"}
        {--categories= : eBay category id(s) to use instead of the automatic one (empty string = no category filter)}
        {--limit= : Listings to fetch (1-200)}
        {--markets= : Override EBAY_MARKETS, e.g. "EBAY_US:US,EBAY_DE:DE"}
        {--fresh : Bypass the price cache}
        {--token : Only check that an OAuth application token can be minted}';

    protected $description = 'Check eBay Browse API credentials and fetch a price estimate for a card';

    public function handle(EbayPriceService $ebay): int
    {
        $this->line(sprintf('eBay environment: <info>%s</info> (%s)', $ebay->environment(), $ebay->host()));

        try {
            $token = $ebay->accessToken();
            $this->line('OAuth application token: <info>OK</info> ('.strlen($token).' chars)');

            if ($this->option('token')) {
                return self::SUCCESS;
            }

            $options = array_filter([
                'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
                'markets' => $this->option('markets') ?: null,
                'fresh' => (bool) $this->option('fresh'),
            ]);

            if ($this->option('categories') !== null) {
                $options['category_ids'] = $this->option('categories');
            }

            $result = $ebay->estimate($this->argument('query'), $options);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['metric', 'value'], [
            ['markets', count($result['marketplaces']).' ('.collect($result['marketplaces'])->where('error', null)->count().' answered)'],
            ['category', trim(($result['category']['id'] ?? '-').' '.($result['category']['name'] ?? '')).' ('.($result['category']['source'] ?? 'none').')'],
            ['ESTIMATED SALE', $result['estimated_sale']
                ? $result['estimated_sale']['price'].' '.$result['currency'].' ('.$result['estimated_sale']['basis'].', n='.$result['estimated_sale']['sample_size'].', '.$result['estimated_sale']['confidence'].' confidence)'
                : '-'],
            ['condition', $result['condition_filter'].' ('.$result['excluded_listings'].' other listings left out)'],
            ['total matches', $result['total_matches']],
            ['sample / used', $result['sample_size'].' / '.$result['used_for_stats']],
            ['min', $result['min']],
            ['median (asking)', $result['median']],
            ['average', $result['average']],
            ['max', $result['max']],
            ['currency', $result['currency'].' (ECB '.($result['exchange_rates']['date'] ?? 'n/a').')'],
        ]);

        $this->table(['market', 'matches', 'used', 'median '.$result['currency'], 'local', 'note'], array_map(fn ($m) => [
            $m['marketplace_id'].':'.$m['seller_country'],
            $m['total_matches'],
            $m['sample_size'],
            $m['median'] ?? '-',
            $m['local_currency'] ?? '-',
            $m['error'] ? mb_strimwidth($m['error'], 0, 50, '…') : ($m['category_dropped'] ? 'no category filter' : ''),
        ], $result['marketplaces']));

        foreach ($result['items'] as $item) {
            $this->line(sprintf('  %8.2f %s  %-8s %12s  %s', $item['price'], $item['currency'], $item['marketplace_id'], $item['original_price'].' '.$item['original_currency'], mb_strimwidth((string) $item['title'], 0, 70, '…')));
        }

        return self::SUCCESS;
    }
}
