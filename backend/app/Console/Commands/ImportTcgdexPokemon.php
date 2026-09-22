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

    /**
     * finish flag => [variant_name, variant_type, sort_order]. 'firstEdition'
     * is deliberately NOT here — it's an edition, handled as a modifier on
     * top of these finishes (see the per-card loop), not a finish itself.
     */
    private const VARIANT_LABELS = [
        'normal' => ['Normal', 'normal', 1],
        'reverse' => ['Reverse Holo', 'reverse_holo', 2],
        'holo' => ['Holo', 'holo', 3],
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
        $setCardCountChecks = [];
        $firstEditionFindings = [];
        $variantHasSourceImage = []; // variant_key => bool, kept out of variantRows so the CSV columns stay exact

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

            // The set's own cardCount.total is TCGdex's independent tally —
            // comparing it to len(cards) catches the API's card list and its
            // own summary count disagreeing, which a plain source/imported
            // diff (computed from the same cards array) could never catch.
            if (($set['cardCount']['total'] ?? null) !== null) {
                $setCardCountChecks[] = [
                    'set_key' => $setKey,
                    'api_card_count_total' => $set['cardCount']['total'],
                    'cards_array_length' => count($cardBriefs),
                ];
            }

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

                // 'firstEdition' is an EDITION, not a finish — it modifies a
                // finish ("Holo, 1st Edition" vs "Holo, Unlimited"), it isn't
                // a peer option alongside normal/reverse/holo. So finishes
                // and edition are resolved separately, then combined.
                $flags = $card['variants'] ?? [];
                $trueFinishes = array_keys(array_filter(array_intersect_key($flags, self::VARIANT_LABELS)));
                if ($trueFinishes === []) {
                    $trueFinishes = ['normal']; // schema requires >=1 variant per card
                }
                $hasFirstEdition = ($flags['firstEdition'] ?? false) === true;

                // variants_detailed is per TCGdex's own docs not fully clean
                // (duplicate-looking entries for the same real printing), but
                // an entry's (type, "1st-edition" in stamp) pair is the only
                // signal that ties the edition to a SPECIFIC finish, so it's
                // used only for that yes/no check, never for row counts.
                $stampedFinishes = [];
                foreach ($card['variants_detailed'] ?? [] as $vd) {
                    if (in_array('1st-edition', $vd['stamp'] ?? [], true) && isset($vd['type'])) {
                        $stampedFinishes[$vd['type']] = true;
                    }
                }

                $baseImage = $card['image'] ?? null;
                $generatedFirstEditionFinishes = [];
                $inferredWithoutStampConfirmation = [];
                $skippedUnresolvedFinishes = [];

                foreach ($trueFinishes as $finish) {
                    if (! isset(self::VARIANT_LABELS[$finish])) {
                        continue;
                    }
                    [$variantName, $variantType, $sortOrder] = self::VARIANT_LABELS[$finish];
                    $this->addVariantRow($variantRows, $variantHasSourceImage, $cardKey, $variantType, $variantName, $sortOrder, $card, $baseImage);

                    if (! $hasFirstEdition) {
                        continue;
                    }

                    $confirmedByStamp = isset($stampedFinishes[$finish]);
                    if (! $confirmedByStamp && count($trueFinishes) > 1) {
                        // >1 finish exists and nothing ties the 1st-edition
                        // stamp to *this* one specifically — don't guess.
                        $skippedUnresolvedFinishes[] = $finish;

                        continue;
                    }

                    $this->addVariantRow(
                        $variantRows,
                        $variantHasSourceImage,
                        $cardKey,
                        "{$variantType}_1st_edition",
                        "{$variantName} (1st Edition)",
                        $sortOrder + 10,
                        $card,
                        $baseImage,
                    );
                    $generatedFirstEditionFinishes[] = $finish;
                    if (! $confirmedByStamp) {
                        $inferredWithoutStampConfirmation[] = $finish;
                    }
                }

                if ($hasFirstEdition) {
                    $firstEditionFindings[] = [
                        'card_key' => $cardKey,
                        'finish_flags_true' => $trueFinishes,
                        'stamped_finish_types' => array_keys($stampedFinishes),
                        'generated_1st_edition_finishes' => $generatedFirstEditionFinishes,
                        'inferred_without_stamp_confirmation' => $inferredWithoutStampConfirmation,
                        'skipped_unresolved_finishes' => $skippedUnresolvedFinishes,
                        // Warning-worthy whenever we couldn't cleanly confirm
                        // the edition/finish pairing from variants_detailed —
                        // either we guessed (inferred) or gave up (skipped).
                        'ambiguous' => $inferredWithoutStampConfirmation !== [] || $skippedUnresolvedFinishes !== [],
                    ];
                }
            }
        }

        $this->writeCsvs($outDir, $setRows, $cardRows, $variantRows, $externalIdRows);

        $report = $this->buildReport($setsToFetch, $sourceCardCount, $setRows, $cardRows, $variantRows, $externalIdRows, $setCardCountChecks, $variantHasSourceImage, $firstEditionFindings);
        $this->printReport($report);
        file_put_contents("{$outDir}/validation_report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("CSVs + validation_report.json written to: {$outDir}");

        if (! $this->option('apply')) {
            $this->comment('Dry-run only — nothing was written to the database. Pass --apply to import (runs on PASS or PASS_WITH_WARNINGS, refused on FAIL).');

            return self::SUCCESS;
        }

        if ($report['status'] === 'FAIL') {
            $this->error('Validation FAILed — refusing to --apply. Fix the issues above (or re-run without --apply to just inspect the CSVs) first.');

            return self::FAILURE;
        }

        $this->info("Validation {$report['status']} — running cardora:binder-import-v2...");
        $exit = $this->call('cardora:binder-import-v2', ['--path' => $outDir]);

        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<int,array<string,mixed>> $variantRows
     * @param array<string,bool> $variantHasSourceImage
     * @param array<string,mixed> $card
     */
    private function addVariantRow(
        array &$variantRows,
        array &$variantHasSourceImage,
        string $cardKey,
        string $variantType,
        string $variantName,
        int $sortOrder,
        array $card,
        ?string $baseImage,
    ): void {
        $variantKey = "{$cardKey}-{$variantType}";
        $variantRows[] = [
            'card_key' => $cardKey,
            'variant_key' => $variantKey,
            'variant_name' => $variantName,
            'variant_type' => $variantType,
            'rarity' => $card['rarity'] ?? '',
            'artist' => $card['illustrator'] ?? '',
            'image_small' => $baseImage ? "{$baseImage}/low.webp" : '',
            'image_large' => $baseImage ? "{$baseImage}/high.webp" : '',
            'sort_order' => $sortOrder,
        ];
        $variantHasSourceImage[$variantKey] = $baseImage !== null;
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
     * @param array<int,array<string,mixed>> $externalIdRows
     * @param array<int,array<string,mixed>> $setCardCountChecks
     * @param array<string,bool> $variantHasSourceImage
     * @param array<int,array<string,mixed>> $firstEditionFindings
     */
    private function buildReport(
        array $setsInScope,
        int $sourceCardCount,
        array $setRows,
        array $cardRows,
        array $variantRows,
        array $externalIdRows,
        array $setCardCountChecks,
        array $variantHasSourceImage,
        array $firstEditionFindings,
    ): array {
        $setKeys = array_column($setRows, 'set_key');
        $cardKeys = array_column($cardRows, 'card_key');
        $variantKeys = array_column($variantRows, 'variant_key');

        // card_key/set_key are derived 1:1 from the TCGdex source id, so a
        // duplicate key here already implies a duplicate source id — but we
        // also check external_ids directly (entity_type=card rows) since
        // that's the literal "source_card_id unique within tcgdex" ask.
        $duplicateSetKeys = $this->duplicates($setKeys);
        $duplicateCardKeys = $this->duplicates($cardKeys);
        $duplicateVariantKeys = $this->duplicates($variantKeys);

        $tcgdexCardExternalIds = array_column(
            array_filter($externalIdRows, fn ($e) => $e['entity_type'] === 'card' && $e['provider'] === 'tcgdex'),
            'external_id',
        );
        $duplicateSourceCardIds = $this->duplicates($tcgdexCardExternalIds);

        $setKeySet = array_flip($setKeys);
        $orphanCards = array_values(array_filter($cardRows, fn ($c) => ! isset($setKeySet[$c['set_key']])));

        $cardKeySet = array_flip($cardKeys);
        $orphanVariants = array_values(array_filter($variantRows, fn ($v) => ! isset($cardKeySet[$v['card_key']])));

        // Split by whose fault it is: TCGdex simply not having the image yet
        // (upstream, doesn't block — TCGdex's own docs say a missing `image`
        // means "not added to their DB yet") vs. TCGdex giving us an image
        // but our own CSV row ending up empty/malformed (our bug, blocks).
        $upstreamMissingImages = [];
        $importerMissingImages = [];
        foreach ($variantRows as $v) {
            $hadSource = $variantHasSourceImage[$v['variant_key']] ?? false;
            $urlLooksValid = str_starts_with((string) $v['image_large'], 'http');
            if (! $hadSource && ! $urlLooksValid) {
                $upstreamMissingImages[] = $v['variant_key'];
            } elseif ($hadSource && ! $urlLooksValid) {
                $importerMissingImages[] = $v['variant_key'];
            }
        }

        $totalsInverted = array_values(array_filter($setRows, function ($s) {
            return $s['base_total'] !== '' && $s['numbered_total'] !== '' && (int) $s['base_total'] > (int) $s['numbered_total'];
        }));

        $cardCountMismatches = array_values(array_filter(
            $setCardCountChecks,
            fn ($c) => $c['api_card_count_total'] !== $c['cards_array_length'],
        ));

        $ambiguousFirstEdition = array_values(array_filter($firstEditionFindings, fn ($f) => $f['ambiguous']));

        $setsMissing = count($setsInScope) - count($setRows);
        $cardsMissing = $sourceCardCount - count($cardRows);

        $failCounts = [
            'sets_missing' => max($setsMissing, 0),
            'cards_missing' => max($cardsMissing, 0),
            'duplicate_set_keys' => count($duplicateSetKeys),
            'duplicate_card_keys' => count($duplicateCardKeys),
            'duplicate_variant_keys' => count($duplicateVariantKeys),
            'duplicate_source_card_ids' => count($duplicateSourceCardIds),
            'orphan_cards' => count($orphanCards),
            'orphan_variants' => count($orphanVariants),
            'totals_inverted' => count($totalsInverted),
            'card_count_mismatches' => count($cardCountMismatches),
            'importer_missing_images' => count($importerMissingImages),
            'fetch_failures' => count($this->failures),
        ];
        $warningCounts = [
            'upstream_missing_images' => count($upstreamMissingImages),
            'ambiguous_first_edition_mappings' => count($ambiguousFirstEdition),
        ];

        $status = match (true) {
            array_sum($failCounts) > 0 => 'FAIL',
            array_sum($warningCounts) > 0 => 'PASS_WITH_WARNINGS',
            default => 'PASS',
        };

        return [
            'status' => $status,
            'sets' => ['source' => count($setsInScope), 'imported' => count($setRows), 'missing' => max($setsMissing, 0)],
            'cards' => ['source' => $sourceCardCount, 'imported' => count($cardRows), 'missing' => max($cardsMissing, 0)],
            'variants' => ['imported' => count($variantRows)],
            'duplicate_set_keys' => $duplicateSetKeys,
            'duplicate_card_keys' => $duplicateCardKeys,
            'duplicate_variant_keys' => $duplicateVariantKeys,
            'duplicate_source_card_ids' => $duplicateSourceCardIds,
            'orphan_cards' => array_column($orphanCards, 'card_key'),
            'orphan_variants' => array_column($orphanVariants, 'variant_key'),
            'upstream_missing_images' => $upstreamMissingImages,
            'importer_missing_images' => $importerMissingImages,
            'totals_inverted' => array_column($totalsInverted, 'set_key'),
            'card_count_mismatches' => $cardCountMismatches,
            'cards_with_first_edition' => count($firstEditionFindings),
            'cards_with_first_edition_and_multiple_finishes' => count(array_filter($firstEditionFindings, fn ($f) => count($f['finish_flags_true']) > 1)),
            'ambiguous_first_edition_mappings' => array_map(fn ($f) => $f['card_key'], $ambiguousFirstEdition),
            'ambiguous_first_edition_details' => $ambiguousFirstEdition,
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
            ['Duplicate variant_key', count($r['duplicate_variant_keys'])],
            ['Duplicate source_card_id (tcgdex)', count($r['duplicate_source_card_ids'])],
            ['Orphan cards', count($r['orphan_cards'])],
            ['Orphan variants', count($r['orphan_variants'])],
            ['Upstream missing images (WARNING)', count($r['upstream_missing_images'])],
            ['Importer missing images (FAIL)', count($r['importer_missing_images'])],
            ['base_total > numbered_total', count($r['totals_inverted'])],
            ['Set card-count mismatches (API total vs fetched)', count($r['card_count_mismatches'])],
            ['Cards with first edition', $r['cards_with_first_edition']],
            ['  ...with >1 finish flag true', $r['cards_with_first_edition_and_multiple_finishes']],
            ['  ...ambiguous mapping (WARNING)', count($r['ambiguous_first_edition_mappings'])],
            ['Fetch failures', count($r['fetch_failures'])],
            ['Status', $r['status']],
        ]);

        $listKeys = [
            'duplicate_card_keys', 'duplicate_set_keys', 'duplicate_variant_keys', 'duplicate_source_card_ids',
            'orphan_cards', 'orphan_variants', 'totals_inverted', 'importer_missing_images',
        ];
        foreach ($listKeys as $key) {
            if ($r[$key] !== []) {
                $this->warn(ucfirst(str_replace('_', ' ', $key)) . ': ' . implode(', ', array_slice($r[$key], 0, 20)));
            }
        }
        if ($r['upstream_missing_images'] !== []) {
            $this->comment('Upstream missing images (TCGdex has no image field yet, not our fault): ' . count($r['upstream_missing_images']) . ' variants, e.g. ' . implode(', ', array_slice($r['upstream_missing_images'], 0, 5)));
        }
        foreach ($r['ambiguous_first_edition_details'] as $f) {
            $this->warn(sprintf(
                '  ambiguous first-edition: %s (finishes: [%s], stamp confirms: [%s], generated 1st-ed rows for: [%s], inferred w/o stamp: [%s], skipped unresolved: [%s])',
                $f['card_key'],
                implode(',', $f['finish_flags_true']),
                implode(',', $f['stamped_finish_types']),
                implode(',', $f['generated_1st_edition_finishes']),
                implode(',', $f['inferred_without_stamp_confirmation']),
                implode(',', $f['skipped_unresolved_finishes']),
            ));
        }
        foreach ($r['card_count_mismatches'] as $m) {
            $this->warn("  set {$m['set_key']}: API cardCount.total={$m['api_card_count_total']} but fetched {$m['cards_array_length']} cards");
        }
        foreach ($this->failures as $f) {
            $this->warn("  fetch failure [{$f['type']}] {$f['id']}: {$f['reason']}");
        }
    }
}
