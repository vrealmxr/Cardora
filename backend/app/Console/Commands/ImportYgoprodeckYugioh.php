<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fetches Yu-Gi-Oh! TCG catalog data from YGOPRODeck (db.ygoprodeck.com,
 * public, no API key) and writes it out as the same 5-CSV format
 * cardora:binder-import-v2 consumes — it does NOT write to the database
 * itself.
 *
 * YGOPRODeck's data model is inverted from TCGdex's: one card object (keyed
 * by a name-level numeric id) carries a `card_sets` array of every historical
 * printing (set_name, set_code, set_rarity) — there is no separate per-set
 * "list this set's cards" call. So instead of Set→Card, this importer
 * builds canonical Sets from cardsets.php, then derives canonical Cards by
 * GROUPING every card's card_sets entries by (set, set_code) — set_code
 * (e.g. "LOB-005") already encodes the printing position, so it doubles as
 * our collector_number. Variants are the distinct rarities seen for the
 * same (set, set_code) — confirmed real in the data (e.g. Quarter Century
 * Bonanza RA03-EN080 has both a Platinum Secret Rare and a Quarter Century
 * Secret Rare row).
 *
 * Images are attached to the CARD NAME, not to a specific set_code/rarity —
 * there is no field linking a specific artwork to a specific printing. A
 * card with exactly one image (99.1% of the database) uses it as a
 * card-level fallback for every variant derived from that name
 * (image_mapping_status=fallback_card_artwork); a card with >1 image is
 * genuinely ambiguous and gets no image at all rather than a guess
 * (image_mapping_status=ambiguous_multiple_artworks) — flagged for a later
 * enrichment pass, never invented.
 *
 * Dry-run by default: always generates the CSVs + a global validation
 * report. Pass --apply to additionally hand the generated directory to
 * cardora:binder-import-v2, and only if the report is PASS or
 * PASS_WITH_WARNINGS.
 */
class ImportYgoprodeckYugioh extends Command
{
    protected $signature = 'cardora:ygoprodeck-import-yugioh
        {--out= : Directory to write games/sets/cards/variants/external_ids CSVs into (default: storage/app/ygoprodeck-yugioh)}
        {--apply : Also run cardora:binder-import-v2 on the generated CSVs. Only runs on PASS or PASS_WITH_WARNINGS. Without this flag nothing touches the database.}
        {--refresh-cache : Ignore the persistent on-disk cache and re-fetch from the API (results still get cached)}';

    protected $description = 'Fetch Yu-Gi-Oh! catalog data from YGOPRODeck and generate catalog v2 import CSVs, with a dry-run validation report';

    private const CARDINFO_URL = 'https://db.ygoprodeck.com/api/v7/cardinfo.php';
    private const CARDSETS_URL = 'https://db.ygoprodeck.com/api/v7/cardsets.php';

    private int $apiRequestsFailed = 0;
    private int $apiRequestsRetried = 0;
    private int $cacheHits = 0;

    private array $setRows = [];
    private array $cardRows = [];
    private array $variantRows = [];
    private array $externalIdRows = [];
    private array $setSourceCardCounts = []; // set_key => num_of_cards per cardsets.php
    private array $setActualCardCounts = []; // set_key => distinct (set,set_code) groups actually built

    private array $upstreamMissingCardData = []; // canonical set has 0 grouped cards despite num_of_cards > 0
    private array $expectedUniqueVsNumberedDifferences = []; // grouped card count != num_of_cards, but non-zero

    private array $unresolvedSetReferences = []; // card_sets entries pointing at a set_name not in cardsets.php
    private int $skillCardsIncluded = 0;
    private int $physicalTokensIncluded = 0;
    private int $tokensExcludedNonphysical = 0;
    private array $unresolvedPhysicalTokens = [];

    private int $fallbackArtworkCards = 0;
    private int $ambiguousArtworkCards = 0;
    private int $noArtworkCards = 0;

    private int $nonUniqueSetCodesSkipped = 0; // set_code shared by >1 set_name — external_id row skipped, set row itself unaffected
    private int $nonUniqueCardCodesSkipped = 0; // full set_code shared by >1 set — external_id row skipped, card row itself unaffected

    public function handle(): int
    {
        $outDir = rtrim((string) ($this->option('out') ?: storage_path('app/ygoprodeck-yugioh')), '/');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");

            return self::FAILURE;
        }

        $gitCommit = $this->currentGitCommit();
        $this->info("Importer version: {$gitCommit['hash']}" . ($gitCommit['dirty'] ? ' (dirty working tree)' : ''));

        $this->info('Fetching cardsets.php (canonical set list)...');
        $allSets = $this->getJson(self::CARDSETS_URL);
        if ($allSets === null) {
            $this->error('Could not reach YGOPRODeck cardsets.php — aborting.');

            return self::FAILURE;
        }
        $this->info('Sets: ' . count($allSets));

        $this->info('Fetching cardinfo.php (full card database, one bulk call)...');
        $cardInfoResponse = $this->getJson(self::CARDINFO_URL);
        if ($cardInfoResponse === null) {
            $this->error('Could not reach YGOPRODeck cardinfo.php — aborting.');

            return self::FAILURE;
        }
        $allCards = $cardInfoResponse['data'] ?? [];
        $this->info('Cards (by name/id): ' . count($allCards));

        // canonical set_name => ['set_code'=>.., 'num_of_cards'=>.., 'tcg_date'=>..]
        // Keyed by set_name, NOT set_code: set_code is a reused "product
        // code" in this data (e.g. "YS15" names 3 unrelated Starter Decks,
        // "MRD-EN061" is shared between "Metal Raiders" and its own 25th
        // Anniversary reprint) — set_name is the only field confirmed
        // unique across all 1035 rows.
        $canonicalSets = [];
        foreach ($allSets as $s) {
            $canonicalSets[$s['set_name']] = $s;
        }

        // How many distinct sets share the same raw set_code — used below
        // to decide whether that code is safe to store as a unique
        // external_id, or must be skipped to avoid silently colliding two
        // different sets' rows in binder_card_external_ids.
        $setNamesByCode = [];
        foreach ($canonicalSets as $setName => $s) {
            $setNamesByCode[$s['set_code']][] = $setName;
        }

        // Register every canonical set as a Set row up front (even ones no
        // card ends up grouped under yet) so it's visible in the report.
        foreach ($canonicalSets as $setName => $s) {
            $setKey = $this->toSetKey($setName);
            $this->setRows[$setKey] = [
                'game_slug' => 'yugioh',
                'set_key' => $setKey,
                'set_name' => $setName,
                'abbreviation' => $s['set_code'],
                'set_code' => $s['set_code'],
                'set_type' => 'main',
                'language' => 'EN',
                'region' => '',
                'released_at' => $s['tcg_date'] ?? '',
                'base_total' => $s['num_of_cards'] ?? '',
                'numbered_total' => $s['num_of_cards'] ?? '',
                'source' => 'ygoprodeck',
                'source_set_id' => $setName,
                'source_url' => self::CARDSETS_URL,
            ];
            if (count($setNamesByCode[$s['set_code']]) === 1) {
                $this->externalIdRows[] = [
                    'entity_type' => 'set',
                    'entity_key' => $setKey,
                    'provider' => 'ygoprodeck',
                    'external_id' => $s['set_code'],
                    'external_type' => 'set_code',
                    'external_url' => '',
                ];
            } else {
                $this->nonUniqueSetCodesSkipped++;
            }
            $this->setSourceCardCounts[$setKey] = (int) ($s['num_of_cards'] ?? 0);
        }

        // group[$setKey][$fullSetCode] = ['card_name'=>, 'card_type'=>, 'rarities'=>[rarity=>true], 'image'=>...]
        $groups = [];

        foreach ($allCards as $card) {
            $cardSets = $card['card_sets'] ?? [];
            $isToken = ($card['type'] ?? '') === 'Token';
            $isSkill = ($card['type'] ?? '') === 'Skill Card';

            if ($isToken && $cardSets === []) {
                $this->tokensExcludedNonphysical++;

                continue;
            }

            $imageInfo = $this->resolveImage($card);

            $resolvedAny = false;
            foreach ($cardSets as $cs) {
                $setName = $cs['set_name'] ?? null;
                $fullSetCode = $cs['set_code'] ?? null;
                if ($setName === null || $fullSetCode === null || ! isset($canonicalSets[$setName])) {
                    $entry = ['card_name' => $card['name'] ?? '?', 'set_name' => $setName, 'set_code' => $fullSetCode];
                    $this->unresolvedSetReferences[] = $entry;
                    if ($isToken) {
                        $this->unresolvedPhysicalTokens[] = $entry;
                    }

                    continue;
                }

                $resolvedAny = true;
                $setKey = $this->toSetKey($setName);
                $rarity = $cs['set_rarity'] ?? '';

                if (! isset($groups[$setKey][$fullSetCode])) {
                    $groups[$setKey][$fullSetCode] = [
                        'card_name' => $card['name'] ?? $fullSetCode,
                        'card_type' => $card['type'] ?? '',
                        'ygoprodeck_id' => $card['id'] ?? null,
                        'is_token' => $isToken,
                        'rarities' => [],
                        'image' => $imageInfo,
                    ];
                }
                $groups[$setKey][$fullSetCode]['rarities'][$rarity] = true;
            }

            if ($isSkill && $resolvedAny) {
                $this->skillCardsIncluded++;
            }
            if ($isToken && $resolvedAny) {
                $this->physicalTokensIncluded++;
            }
        }

        // Full set_codes (e.g. "MRD-EN061") that appear under more than one
        // set_key (e.g. "Metal Raiders" and its 25th Anniversary reprint,
        // which reuse the same code) — their cards are still created as
        // distinct rows per real set (namespaced card_key below), but the
        // raw code can't be stored as a unique external_id for both.
        $setKeysByFullCode = [];
        foreach ($groups as $setKey => $cardsInSet) {
            foreach (array_keys($cardsInSet) as $fullSetCode) {
                $setKeysByFullCode[$fullSetCode][$setKey] = true;
            }
        }

        foreach ($groups as $setKey => $cardsInSet) {
            $this->setActualCardCounts[$setKey] = count($cardsInSet);

            foreach ($cardsInSet as $fullSetCode => $group) {
                $cardKey = $this->toCardKey($setKey, $fullSetCode);
                $this->cardRows[] = [
                    'game_slug' => 'yugioh',
                    'set_key' => $setKey,
                    'card_key' => $cardKey,
                    'card_name' => $group['card_name'],
                    'clean_name' => $group['card_name'],
                    'collector_number' => $fullSetCode,
                    'card_type' => $group['card_type'],
                    'is_promo' => 'FALSE',
                    'is_token' => $group['is_token'] ? 'TRUE' : 'FALSE',
                    'language' => 'EN',
                ];
                if (count($setKeysByFullCode[$fullSetCode]) === 1) {
                    $this->externalIdRows[] = [
                        'entity_type' => 'card',
                        'entity_key' => $cardKey,
                        'provider' => 'ygoprodeck',
                        'external_id' => $fullSetCode,
                        'external_type' => 'set_code',
                        'external_url' => '',
                    ];
                } else {
                    $this->nonUniqueCardCodesSkipped++;
                }

                $sortOrder = 1;
                foreach (array_keys($group['rarities']) as $rarity) {
                    $variantType = $this->slug($rarity ?: 'unknown');
                    $this->variantRows[] = [
                        'card_key' => $cardKey,
                        'variant_key' => "{$cardKey}-{$variantType}",
                        'variant_name' => $rarity ?: 'Unknown',
                        'variant_type' => $variantType,
                        'rarity' => $rarity,
                        'artist' => '',
                        'image_small' => $group['image']['small'],
                        'image_large' => $group['image']['large'],
                        'sort_order' => $sortOrder++,
                    ];
                }
            }
        }

        // Same three-way classification used for the Pokémon importer:
        // zero grouped cards despite a non-zero canonical count is a real
        // gap; a non-zero-but-different count is normal (e.g. a set whose
        // cards were never assigned card_sets entries by contributors yet).
        foreach ($this->setSourceCardCounts as $setKey => $expected) {
            $actual = $this->setActualCardCounts[$setKey] ?? 0;
            if ($expected === $actual) {
                continue;
            }
            $entry = ['set_key' => $setKey, 'expected' => $expected, 'actual' => $actual];
            if ($actual === 0 && $expected > 0) {
                $this->upstreamMissingCardData[] = $entry;
            } else {
                $this->expectedUniqueVsNumberedDifferences[] = $entry;
            }
        }

        $this->writeCsvs($outDir);

        $report = $this->buildReport($gitCommit);
        $this->printReport($report);
        file_put_contents("{$outDir}/validation_report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("CSVs + validation_report.json written to: {$outDir}");

        if (! $this->option('apply')) {
            $this->comment('Dry-run only — nothing was written to the database. Pass --apply to import (runs on PASS or PASS_WITH_WARNINGS, refused on FAIL).');

            return self::SUCCESS;
        }

        if ($report['status'] === 'FAIL') {
            $this->error('Validation FAILed — refusing to --apply.');

            return self::FAILURE;
        }

        $this->info("Validation {$report['status']} — running cardora:binder-import-v2...");
        $exit = $this->call('cardora:binder-import-v2', ['--path' => $outDir]);

        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string,mixed> $card @return array{small:string,large:string,status:string} */
    private function resolveImage(array $card): array
    {
        $images = $card['card_images'] ?? [];
        if (count($images) === 1) {
            $this->fallbackArtworkCards++;

            return ['small' => $images[0]['image_url_small'] ?? '', 'large' => $images[0]['image_url'] ?? '', 'status' => 'fallback_card_artwork'];
        }
        if (count($images) > 1) {
            $this->ambiguousArtworkCards++;

            return ['small' => '', 'large' => '', 'status' => 'ambiguous_multiple_artworks'];
        }
        $this->noArtworkCards++;

        return ['small' => '', 'large' => '', 'status' => 'no_artwork'];
    }

    private function toSetKey(string $setName): string
    {
        return 'yugioh-' . $this->slug($setName);
    }

    private function toCardKey(string $setKey, string $fullSetCode): string
    {
        // Namespaced by set, not just the code: the same full set_code can
        // legitimately belong to two different real sets (see class
        // docblock), so the code alone isn't a safe global card_key.
        return "{$setKey}-" . $this->slug($fullSetCode);
    }

    private function slug(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value));
    }

    /** @return array{hash: string, short: string, dirty: bool} */
    private function currentGitCommit(): array
    {
        $repoDir = escapeshellarg(base_path());
        $hash = trim((string) shell_exec("git -C {$repoDir} rev-parse HEAD 2>&1"));
        $short = trim((string) shell_exec("git -C {$repoDir} rev-parse --short HEAD 2>&1"));
        $dirty = trim((string) shell_exec("git -C {$repoDir} status --porcelain -- app/Console/Commands/ImportYgoprodeckYugioh.php 2>&1")) !== '';

        return [
            'hash' => str_starts_with($hash, 'fatal') ? 'unknown' : $hash,
            'short' => str_starts_with($short, 'fatal') ? 'unknown' : $short,
            'dirty' => $dirty,
        ];
    }

    private function getJson(string $url): ?array
    {
        $cacheFile = storage_path('app/ygoprodeck-cache/' . sha1($url) . '.json');
        if (! $this->option('refresh-cache') && is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if ($cached !== null) {
                $this->cacheHits++;

                return $cached;
            }
        }

        $maxAttempts = 3;
        $result = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $isLastAttempt = $attempt === $maxAttempts;
            try {
                $response = Http::timeout(60)->get($url);
            } catch (\Throwable $e) {
                $this->apiRequestsFailed++;
                if (! $isLastAttempt) {
                    $this->apiRequestsRetried++;
                    usleep(500_000 * (2 ** ($attempt - 1)));

                    continue;
                }
                $this->warn("  fetch failed: {$url} ({$e->getMessage()})");
                break;
            }
            if ($response->successful()) {
                $result = $response->json();
                break;
            }
            $this->apiRequestsFailed++;
            if (! $isLastAttempt) {
                $this->apiRequestsRetried++;
                usleep(500_000 * (2 ** ($attempt - 1)));

                continue;
            }
            $this->warn("  fetch failed: {$url} (HTTP {$response->status()})");
        }

        if ($result !== null) {
            $dir = dirname($cacheFile);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($cacheFile, json_encode($result));
        }

        return $result;
    }

    private function writeCsvs(string $outDir): void
    {
        $this->writeCsv("{$outDir}/games.csv", ['slug', 'name', 'category', 'sort_order'], [
            ['yugioh', 'Yu-Gi-Oh!', 'tcg', 20],
        ]);
        $this->writeCsv("{$outDir}/sets.csv", [
            'game_slug', 'set_key', 'set_name', 'abbreviation', 'set_code', 'set_type', 'language',
            'region', 'released_at', 'base_total', 'numbered_total', 'source', 'source_set_id', 'source_url',
        ], array_values($this->setRows));
        $this->writeCsv("{$outDir}/cards.csv", [
            'game_slug', 'set_key', 'card_key', 'card_name', 'clean_name', 'collector_number',
            'card_type', 'is_promo', 'is_token', 'language',
        ], $this->cardRows);
        $this->writeCsv("{$outDir}/variants.csv", [
            'card_key', 'variant_key', 'variant_name', 'variant_type', 'rarity', 'artist',
            'image_small', 'image_large', 'sort_order',
        ], $this->variantRows);
        $this->writeCsv("{$outDir}/external_ids.csv", [
            'entity_type', 'entity_key', 'provider', 'external_id', 'external_type', 'external_url',
        ], $this->externalIdRows);
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

    private function duplicates(array $values): array
    {
        $counts = array_count_values($values);

        return array_keys(array_filter($counts, fn ($n) => $n > 1));
    }

    /** @param array{hash:string,short:string,dirty:bool} $gitCommit */
    private function buildReport(array $gitCommit): array
    {
        $setKeys = array_column($this->setRows, 'set_key');
        $cardKeys = array_column($this->cardRows, 'card_key');
        $variantKeys = array_column($this->variantRows, 'variant_key');

        $duplicateSetKeys = $this->duplicates($setKeys);
        $duplicateCardKeys = $this->duplicates($cardKeys);
        $duplicateVariantKeys = $this->duplicates($variantKeys);

        $ygoCardExternalIds = array_column(
            array_filter($this->externalIdRows, fn ($e) => $e['entity_type'] === 'card' && $e['provider'] === 'ygoprodeck'),
            'external_id',
        );
        $duplicateSourceCardIds = $this->duplicates($ygoCardExternalIds);

        $setKeySet = array_flip($setKeys);
        $orphanCards = array_values(array_filter($this->cardRows, fn ($c) => ! isset($setKeySet[$c['set_key']])));
        $cardKeySet = array_flip($cardKeys);
        $orphanVariants = array_values(array_filter($this->variantRows, fn ($v) => ! isset($cardKeySet[$v['card_key']])));

        $failCounts = [
            'duplicate_set_keys' => count($duplicateSetKeys),
            'duplicate_card_keys' => count($duplicateCardKeys),
            'duplicate_variant_keys' => count($duplicateVariantKeys),
            'duplicate_source_card_ids' => count($duplicateSourceCardIds),
            'orphan_cards' => count($orphanCards),
            'orphan_variants' => count($orphanVariants),
            'upstream_missing_card_data' => count($this->upstreamMissingCardData),
        ];
        $warningCounts = [
            'expected_unique_vs_numbered_differences' => count($this->expectedUniqueVsNumberedDifferences),
            'unresolved_set_references' => count($this->unresolvedSetReferences),
            'unresolved_physical_tokens' => count($this->unresolvedPhysicalTokens),
            'ambiguous_artwork_cards' => $this->ambiguousArtworkCards,
            'no_artwork_cards' => $this->noArtworkCards,
        ];

        $status = match (true) {
            array_sum($failCounts) > 0 => 'FAIL',
            array_sum($warningCounts) > 0 => 'PASS_WITH_WARNINGS',
            default => 'PASS',
        };

        return [
            'status' => $status,
            'importer_git_commit' => $gitCommit['hash'],
            'importer_git_commit_short' => $gitCommit['short'],
            'importer_working_tree_dirty' => $gitCommit['dirty'],
            'api_requests_failed' => $this->apiRequestsFailed,
            'api_requests_retried' => $this->apiRequestsRetried,
            'cache_hits' => $this->cacheHits,
            'source_sets' => count($this->setRows),
            'generated_sets_with_cards' => count($this->setActualCardCounts),
            'generated_cards' => count($this->cardRows),
            'generated_variants' => count($this->variantRows),
            'skill_cards_included' => $this->skillCardsIncluded,
            'physical_tokens_included' => $this->physicalTokensIncluded,
            'tokens_excluded_nonphysical' => $this->tokensExcludedNonphysical,
            'unresolved_physical_tokens' => $this->unresolvedPhysicalTokens,
            'duplicate_set_keys' => $duplicateSetKeys,
            'duplicate_card_keys' => $duplicateCardKeys,
            'duplicate_variant_keys' => $duplicateVariantKeys,
            'duplicate_source_card_ids' => $duplicateSourceCardIds,
            'orphan_cards' => array_column($orphanCards, 'card_key'),
            'orphan_variants' => array_column($orphanVariants, 'variant_key'),
            'upstream_missing_card_data' => $this->upstreamMissingCardData,
            'expected_unique_vs_numbered_differences' => $this->expectedUniqueVsNumberedDifferences,
            'unresolved_set_references' => $this->unresolvedSetReferences,
            'image_mapping' => [
                'fallback_card_artwork' => $this->fallbackArtworkCards,
                'ambiguous_multiple_artworks' => $this->ambiguousArtworkCards,
                'no_artwork' => $this->noArtworkCards,
            ],
            'non_unique_set_codes_skipped' => $this->nonUniqueSetCodesSkipped,
            'non_unique_card_codes_skipped' => $this->nonUniqueCardCodesSkipped,
        ];
    }

    private function printReport(array $r): void
    {
        $this->newLine();
        $this->info("=== Validation report (importer {$r['importer_git_commit_short']}) ===");
        $this->table(['Metric', 'Value'], [
            ['api_requests_failed', $r['api_requests_failed']],
            ['api_requests_retried', $r['api_requests_retried']],
            ['cache_hits', $r['cache_hits']],
            ['source_sets', $r['source_sets']],
            ['generated_sets_with_cards', $r['generated_sets_with_cards']],
            ['generated_cards', $r['generated_cards']],
            ['generated_variants', $r['generated_variants']],
            ['skill_cards_included', $r['skill_cards_included']],
            ['physical_tokens_included', $r['physical_tokens_included']],
            ['tokens_excluded_nonphysical', $r['tokens_excluded_nonphysical']],
            ['unresolved_physical_tokens', count($r['unresolved_physical_tokens'])],
            ['duplicate_set_keys', count($r['duplicate_set_keys'])],
            ['duplicate_card_keys', count($r['duplicate_card_keys'])],
            ['duplicate_variant_keys', count($r['duplicate_variant_keys'])],
            ['duplicate_source_card_ids', count($r['duplicate_source_card_ids'])],
            ['orphan_cards', count($r['orphan_cards'])],
            ['orphan_variants', count($r['orphan_variants'])],
            ['upstream_missing_card_data (FAIL)', count($r['upstream_missing_card_data'])],
            ['expected_unique_vs_numbered_differences (WARNING)', count($r['expected_unique_vs_numbered_differences'])],
            ['unresolved_set_references (WARNING)', count($r['unresolved_set_references'])],
            ['image: fallback_card_artwork', $r['image_mapping']['fallback_card_artwork']],
            ['image: ambiguous_multiple_artworks', $r['image_mapping']['ambiguous_multiple_artworks']],
            ['image: no_artwork', $r['image_mapping']['no_artwork']],
            ['non_unique_set_codes_skipped (info)', $r['non_unique_set_codes_skipped']],
            ['non_unique_card_codes_skipped (info)', $r['non_unique_card_codes_skipped']],
            ['STATUS', $r['status']],
        ]);

        foreach (['duplicate_set_keys', 'duplicate_card_keys', 'duplicate_variant_keys', 'duplicate_source_card_ids', 'orphan_cards', 'orphan_variants'] as $key) {
            if ($r[$key] !== []) {
                $this->warn(ucfirst(str_replace('_', ' ', $key)) . ': ' . implode(', ', array_slice($r[$key], 0, 20)));
            }
        }
        foreach (array_slice($r['upstream_missing_card_data'], 0, 20) as $m) {
            $this->error("  upstream_missing_card_data: {$m['set_key']} — expected {$m['expected']}, got {$m['actual']}");
        }
    }
}
