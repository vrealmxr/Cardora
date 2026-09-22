<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fetches Pokémon catalog data from TCGdex (api.tcgdex.net, MIT-licensed,
 * no API key) and writes it out as the same 5-CSV format
 * cardora:binder-import-v2 consumes — it does NOT write to the database
 * itself. Pricing/marketplace fields (Cardmarket, TCGplayer) are read from
 * TCGdex but deliberately NOT written anywhere: TCGdex's own docs note the
 * printing→marketplace-listing mapping isn't fully reliable yet, and
 * pricing is planned as a separate, later market-sync pass, not part of
 * the canonical catalog import.
 *
 * Dry-run by default: always generates the CSVs + a validation report.
 * Pass --apply to additionally hand the generated directory to
 * cardora:binder-import-v2 (the only thing that ever writes to the DB),
 * and only if the report is a clean PASS.
 */
class ImportTcgdexPokemon extends Command
{
    protected $signature = 'cardora:tcgdex-import-pokemon
        {--set=* : TCGdex set id(s) to fetch, e.g. sv03.5. Omit to fetch every English Pokémon set.}
        {--out= : Directory to write games/sets/cards/variants/external_ids CSVs into (default: storage/app/tcgdex-pokemon)}
        {--apply : Also run cardora:binder-import-v2 on the generated CSVs. Only runs if the validation report is a clean PASS. Without this flag nothing touches the database.}
        {--delay-ms=80 : Delay between TCGdex card-detail requests, in milliseconds (politeness against the public API)}';

    protected $description = 'Fetch Pokémon catalog data from TCGdex and generate catalog v2 import CSVs, with a dry-run validation report';

    private const BASE_URL = 'https://api.tcgdex.net/v2/en';

    /** flag => [variant_name, variant_type, sort_order] */
    private const VARIANT_LABELS = [
        'normal' => ['Normal', 'normal', 1],
        'reverse' => ['Reverse Holo', 'reverse_holo', 2],
        'holo' => ['Holo', 'holo', 3],
        'firstEdition' => ['1st Edition', '1st_edition', 4],
        'wPromo' => ['W Promotional', 'w_promo', 5],
    ];

    private array $failures = [];

    public function handle(): int
    {
        $outDir = rtrim((string) ($this->option('out') ?: storage_path('app/tcgdex-pokemon')), '/');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");

            return self::FAILURE;
        }

        $this->info('Fetching set list from TCGdex...');
        $allSets = $this->getJson(self::BASE_URL . '/sets');
        if ($allSets === null) {
            $this->error('Could not reach TCGdex /sets — aborting.');

            return self::FAILURE;
        }

        $wantedIds = array_filter((array) $this->option('set'));
        $setsToFetch = $wantedIds === []
            ? $allSets
            : array_values(array_filter($allSets, fn ($s) => in_array($s['id'], $wantedIds, true)));

        $unknownIds = array_diff($wantedIds, array_column($setsToFetch, 'id'));
        foreach ($unknownIds as $id) {
            $this->warn("  --set={$id} not found in TCGdex /sets, skipping");
        }

        $this->info('Sets in scope: ' . count($setsToFetch));

        $setRows = [];
        $cardRows = [];
        $variantRows = [];
        $externalIdRows = [];
        $sourceCardCount = 0;

        foreach ($setsToFetch as $i => $setBrief) {
            $setId = $setBrief['id'];
            $this->line(sprintf('[%d/%d] Set %s (%s)', $i + 1, count($setsToFetch), $setId, $setBrief['name'] ?? '?'));

            $set = $this->getJson(self::BASE_URL . "/sets/{$setId}");
            if ($set === null) {
                $this->failures[] = ['type' => 'set', 'id' => $setId, 'reason' => 'fetch failed'];

                continue;
            }

            $setKey = 'pokemon-' . strtolower($setId);
            $setRows[] = [
                'game_slug' => 'pokemon',
                'set_key' => $setKey,
                'set_name' => $set['name'] ?? $setId,
                'abbreviation' => $set['abbreviation']['official'] ?? '',
                'set_code' => $setId,
                'set_type' => 'main',
                'language' => 'EN',
                'region' => '',
                'released_at' => $set['releaseDate'] ?? '',
                'base_total' => $set['cardCount']['official'] ?? '',
                'numbered_total' => $set['cardCount']['total'] ?? '',
                'source' => 'tcgdex',
                'source_set_id' => $setId,
                'source_url' => self::BASE_URL . "/sets/{$setId}",
            ];
            $externalIdRows[] = [
                'entity_type' => 'set',
                'entity_key' => $setKey,
                'provider' => 'tcgdex',
                'external_id' => $setId,
                'external_type' => 'set_id',
                'external_url' => '',
            ];

            $cardBriefs = $set['cards'] ?? [];
            $sourceCardCount += count($cardBriefs);

            foreach ($cardBriefs as $cardBrief) {
                $cardId = $cardBrief['id'];
                usleep(((int) $this->option('delay-ms')) * 1000);

                $card = $this->getJson(self::BASE_URL . "/cards/{$cardId}");
                if ($card === null) {
                    $this->failures[] = ['type' => 'card', 'id' => $cardId, 'reason' => 'fetch failed'];

                    continue;
                }

                $cardKey = 'pokemon-' . strtolower($cardId);
                $cardRows[] = [
                    'game_slug' => 'pokemon',
                    'set_key' => $setKey,
                    'card_key' => $cardKey,
                    'card_name' => $card['name'] ?? $cardId,
                    'clean_name' => $card['name'] ?? $cardId,
                    'collector_number' => $card['localId'] ?? '',
                    'card_type' => $card['category'] ?? '',
                    'is_promo' => 'FALSE',
                    'is_token' => 'FALSE',
                    'language' => 'EN',
                ];
                $externalIdRows[] = [
                    'entity_type' => 'card',
                    'entity_key' => $cardKey,
                    'provider' => 'tcgdex',
                    'external_id' => $cardId,
                    'external_type' => 'card_id',
                    'external_url' => '',
                ];

                $flags = $card['variants'] ?? [];
                $trueFlags = array_keys(array_filter($flags));
                if ($trueFlags === []) {
                    $trueFlags = ['normal']; // schema requires >=1 variant per card
                }

                $baseImage = $card['image'] ?? null;
                foreach ($trueFlags as $flag) {
                    [$variantName, $variantType, $sortOrder] = self::VARIANT_LABELS[$flag] ?? [ucfirst($flag), $flag, 9];
                    $variantRows[] = [
                        'card_key' => $cardKey,
                        'variant_key' => "{$cardKey}-{$variantType}",
                        'variant_name' => $variantName,
                        'variant_type' => $variantType,
                        'rarity' => $card['rarity'] ?? '',
                        'artist' => $card['illustrator'] ?? '',
                        'image_small' => $baseImage ? "{$baseImage}/low.webp" : '',
                        'image_large' => $baseImage ? "{$baseImage}/high.webp" : '',
                        'sort_order' => $sortOrder,
                    ];
                }
            }
        }

        $this->writeCsvs($outDir, $setRows, $cardRows, $variantRows, $externalIdRows);

        $report = $this->buildReport($setsToFetch, $sourceCardCount, $setRows, $cardRows, $variantRows);
        $this->printReport($report);
        file_put_contents("{$outDir}/validation_report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("CSVs + validation_report.json written to: {$outDir}");

        if (! $this->option('apply')) {
            $this->comment('Dry-run only — nothing was written to the database. Pass --apply to import (only runs on a PASS).');

            return self::SUCCESS;
        }

        if ($report['status'] !== 'PASS') {
            $this->error('Validation FAILed — refusing to --apply. Fix the issues above (or re-run without --apply to just inspect the CSVs) first.');

            return self::FAILURE;
        }

        $this->info('Validation PASSed — running cardora:binder-import-v2...');
        $exit = $this->call('cardora:binder-import-v2', ['--path' => $outDir]);

        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function getJson(string $url): ?array
    {
        try {
            $response = Http::timeout(20)->retry(2, 300)->get($url);
        } catch (\Throwable $e) {
            $this->warn("  fetch failed: {$url} ({$e->getMessage()})");

            return null;
        }

        if (! $response->successful()) {
            $this->warn("  fetch failed: {$url} (HTTP {$response->status()})");

            return null;
        }

        return $response->json();
    }

    /** @param array<int,array<string,mixed>> $sets @param array<int,array<string,mixed>> $cards @param array<int,array<string,mixed>> $variants @param array<int,array<string,mixed>> $externalIds */
    private function writeCsvs(string $outDir, array $sets, array $cards, array $variants, array $externalIds): void
    {
        $this->writeCsv("{$outDir}/games.csv", ['slug', 'name', 'category', 'sort_order'], [
            ['pokemon', 'Pokémon', 'tcg', 10],
        ]);
        $this->writeCsv("{$outDir}/sets.csv", [
            'game_slug', 'set_key', 'set_name', 'abbreviation', 'set_code', 'set_type', 'language',
            'region', 'released_at', 'base_total', 'numbered_total', 'source', 'source_set_id', 'source_url',
        ], $sets);
        $this->writeCsv("{$outDir}/cards.csv", [
            'game_slug', 'set_key', 'card_key', 'card_name', 'clean_name', 'collector_number',
            'card_type', 'is_promo', 'is_token', 'language',
        ], $cards);
        $this->writeCsv("{$outDir}/variants.csv", [
            'card_key', 'variant_key', 'variant_name', 'variant_type', 'rarity', 'artist',
            'image_small', 'image_large', 'sort_order',
        ], $variants);
        $this->writeCsv("{$outDir}/external_ids.csv", [
            'entity_type', 'entity_key', 'provider', 'external_id', 'external_type', 'external_url',
        ], $externalIds);
    }

    /** @param array<int,string> $header @param array<int,array<string,mixed>> $rows */
    private function writeCsv(string $path, array $header, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    /**
     * @param array<int,array<string,mixed>> $setsInScope
     * @param array<int,array<string,mixed>> $setRows
     * @param array<int,array<string,mixed>> $cardRows
     * @param array<int,array<string,mixed>> $variantRows
     */
    private function buildReport(array $setsInScope, int $sourceCardCount, array $setRows, array $cardRows, array $variantRows): array
    {
        $setKeys = array_column($setRows, 'set_key');
        $cardKeys = array_column($cardRows, 'card_key');

        // card_key/set_key are derived 1:1 from the TCGdex source id, so a
        // duplicate key here already implies a duplicate source id.
        $duplicateSetKeys = $this->duplicates($setKeys);
        $duplicateCardKeys = $this->duplicates($cardKeys);

        $setKeySet = array_flip($setKeys);
        $orphanCards = array_values(array_filter($cardRows, fn ($c) => ! isset($setKeySet[$c['set_key']])));

        $cardKeySet = array_flip($cardKeys);
        $orphanVariants = array_values(array_filter($variantRows, fn ($v) => ! isset($cardKeySet[$v['card_key']])));

        $missingImages = array_values(array_filter($variantRows, fn ($v) => ($v['image_large'] ?? '') === ''));

        $setsMissing = count($setsInScope) - count($setRows);
        $cardsMissing = $sourceCardCount - count($cardRows);

        $failCounts = [
            'sets_missing' => max($setsMissing, 0),
            'cards_missing' => max($cardsMissing, 0),
            'duplicate_set_keys' => count($duplicateSetKeys),
            'duplicate_card_keys' => count($duplicateCardKeys),
            'orphan_cards' => count($orphanCards),
            'orphan_variants' => count($orphanVariants),
            'fetch_failures' => count($this->failures),
        ];
        $status = array_sum($failCounts) === 0 ? 'PASS' : 'FAIL';

        return [
            'status' => $status,
            'sets' => ['source' => count($setsInScope), 'imported' => count($setRows), 'missing' => max($setsMissing, 0)],
            'cards' => ['source' => $sourceCardCount, 'imported' => count($cardRows), 'missing' => max($cardsMissing, 0)],
            'variants' => ['imported' => count($variantRows)],
            'duplicate_set_keys' => $duplicateSetKeys,
            'duplicate_card_keys' => $duplicateCardKeys,
            'orphan_cards' => array_column($orphanCards, 'card_key'),
            'orphan_variants' => array_column($orphanVariants, 'variant_key'),
            'missing_images' => array_column($missingImages, 'variant_key'),
            'fetch_failures' => $this->failures,
        ];
    }

    private function duplicates(array $values): array
    {
        $counts = array_count_values($values);

        return array_keys(array_filter($counts, fn ($n) => $n > 1));
    }

    private function printReport(array $r): void
    {
        $this->newLine();
        $this->info('=== Validation report ===');
        $this->table(['Metric', 'Value'], [
            ['Sets — source', $r['sets']['source']],
            ['Sets — imported', $r['sets']['imported']],
            ['Sets — missing', $r['sets']['missing']],
            ['Cards — source', $r['cards']['source']],
            ['Cards — imported', $r['cards']['imported']],
            ['Cards — missing', $r['cards']['missing']],
            ['Variants — imported', $r['variants']['imported']],
            ['Duplicate card_key', count($r['duplicate_card_keys'])],
            ['Duplicate set_key', count($r['duplicate_set_keys'])],
            ['Orphan cards', count($r['orphan_cards'])],
            ['Orphan variants', count($r['orphan_variants'])],
            ['Missing images', count($r['missing_images'])],
            ['Fetch failures', count($r['fetch_failures'])],
            ['Status', $r['status']],
        ]);

        foreach (['duplicate_card_keys', 'duplicate_set_keys', 'orphan_cards', 'orphan_variants'] as $key) {
            if ($r[$key] !== []) {
                $this->warn(ucfirst(str_replace('_', ' ', $key)) . ': ' . implode(', ', array_slice($r[$key], 0, 20)));
            }
        }
        foreach ($this->failures as $f) {
            $this->warn("  fetch failure [{$f['type']}] {$f['id']}: {$f['reason']}");
        }
    }
}
