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
            ['marketplace', $result['marketplace_id']],
            ['category', trim(($result['category']['id'] ?? '-').' '.($result['category']['name'] ?? '')).' ('.($result['category']['source'] ?? 'none').')'],
            ['total matches', $result['total_matches']],
            ['sample / used', $result['sample_size'].' / '.$result['used_for_stats']],
            ['min', $result['min']],
            ['median', $result['median']],
            ['average', $result['average']],
            ['max', $result['max']],
            ['currency', $result['currency']],
        ]);

        foreach ($result['items'] as $item) {
            $this->line(sprintf('  %8.2f %s  %s', $item['price'], $item['currency'], mb_strimwidth((string) $item['title'], 0, 80, '…')));
        }

        return self::SUCCESS;
    }
}
