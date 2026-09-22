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
 * Dry-run by default: always generates the CSVs + a validation report
 * (global + per-set). Pass --apply to additionally hand the generated
 * directory to cardora:binder-import-v2 (the only thing that ever writes
 * to the DB), and only if the report is PASS or PASS_WITH_WARNINGS — never
 * on FAIL, which includes any permanent fetch failure (we can't know the
 * catalog is complete otherwise).
 */
class ImportTcgdexPokemon extends Command
{
    protected $signature = 'cardora:tcgdex-import-pokemon
        {--set=* : TCGdex set id(s) to fetch, e.g. sv03.5. Omit to fetch every English Pokémon set.}
        {--out= : Directory to write games/sets/cards/variants/external_ids CSVs into (default: storage/app/tcgdex-pokemon)}
        {--apply : Also run cardora:binder-import-v2 on the generated CSVs. Only runs on PASS or PASS_WITH_WARNINGS. Without this flag nothing touches the database.}
        {--delay-ms=80 : Delay between TCGdex card-detail requests, in milliseconds (politeness against the public API)}';

    protected $description = 'Fetch Pokémon catalog data from TCGdex and generate catalog v2 import CSVs, with a global + per-set dry-run validation report';

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

    private array $failures = []; // ['type'=>'set'|'card','set_id'=>..,'card_id'=>?,'reason'=>..]
    private int $apiRequestsFailed = 0; // failed HTTP attempts, including ones later retried successfully
    private int $apiRequestsRetried = 0; // retry attempts issued (attempt #2, #3, ... after a failed attempt)

    public function handle(): int
    {
        $outDir = rtrim((string) ($this->option('out') ?: storage_path('app/tcgdex-pokemon')), '/');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");

            return self::FAILURE;
        }

        $gitCommit = $this->currentGitCommit();
        $this->info("Importer version: {$gitCommit['hash']}" . ($gitCommit['dirty'] ? ' (dirty working tree)' : ''));

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
        $setCardCountChecks = [];
        $firstEditionFindings = [];
        $variantHasSourceImage = []; // variant_key => bool, kept out of variantRows so the CSV columns stay exact
        $setSourceCardCounts = []; // set_key => count(cards) TCGdex's set listing said this set has
        $setMeta = []; // set_key => ['set_id'=>.., 'set_name'=>..], incl. sets whose fetch failed

        foreach ($setsToFetch as $i => $setBrief) {
            $setId = $setBrief['id'];
            $setKey = $this->toSetKey($setId);
            $setMeta[$setKey] = ['set_id' => $setId, 'set_name' => $setBrief['name'] ?? $setId];
            $this->line(sprintf('[%d/%d] Set %s (%s)', $i + 1, count($setsToFetch), $setId, $setBrief['name'] ?? '?'));

            $set = $this->getJson(self::BASE_URL . "/sets/{$setId}");
            if ($set === null) {
                $this->failures[] = ['type' => 'set', 'set_id' => $setId, 'card_id' => null, 'reason' => 'fetch failed'];

                continue;
            }

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
            $setSourceCardCounts[$setKey] = count($cardBriefs);

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
                    $this->failures[] = ['type' => 'card', 'set_id' => $setId, 'card_id' => $cardId, 'reason' => 'fetch failed'];

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
                    $reason = match (true) {
                        $skippedUnresolvedFinishes !== [] => sprintf(
                            'Card has >1 true finish (%s) but variants_detailed 1st-edition stamp does not confirm which one — no 1st-edition variant generated for: %s',
                            implode(',', $trueFinishes),
                            implode(',', $skippedUnresolvedFinishes),
                        ),
                        $inferredWithoutStampConfirmation !== [] => sprintf(
                            'Card has a single finish (%s) but variants_detailed has no 1st-edition-stamped entry to confirm it — generated %s_1st_edition inferred from the global firstEdition flag only',
                            implode(',', $trueFinishes),
                            $inferredWithoutStampConfirmation[0],
                        ),
                        default => '',
                    };
                    $firstEditionFindings[] = [
                        'set_id' => $setId,
                        'set_key' => $setKey,
                        'card_id' => $cardId,
                        'card_key' => $cardKey,
                        'card_name' => $card['name'] ?? $cardId,
                        'raw_variant_flags' => $flags,
                        'variants_detailed' => $card['variants_detailed'] ?? null,
                        'finish_flags_true' => $trueFinishes,
                        'stamped_finish_types' => array_keys($stampedFinishes),
                        'generated_1st_edition_finishes' => $generatedFirstEditionFinishes,
                        'inferred_without_stamp_confirmation' => $inferredWithoutStampConfirmation,
                        'skipped_unresolved_finishes' => $skippedUnresolvedFinishes,
                        'reason' => $reason,
                        // Warning-worthy whenever we couldn't cleanly confirm
                        // the edition/finish pairing from variants_detailed —
                        // either we guessed (inferred) or gave up (skipped).
                        'ambiguous' => $inferredWithoutStampConfirmation !== [] || $skippedUnresolvedFinishes !== [],
                    ];
                }
            }
        }

        $this->writeCsvs($outDir, $setRows, $cardRows, $variantRows, $externalIdRows);

        $cardKeyToSetKey = array_column($cardRows, 'set_key', 'card_key');
        $allScopeSetKeys = array_keys($setMeta);

        $global = $this->computeMetrics(
            $allScopeSetKeys,
            $gitCommit,
            $setMeta,
            $setSourceCardCounts,
            $setRows,
            $cardRows,
            $variantRows,
            $externalIdRows,
            $setCardCountChecks,
            $variantHasSourceImage,
            $firstEditionFindings,
            $cardKeyToSetKey,
        );

        $perSet = [];
        foreach ($allScopeSetKeys as $setKey) {
            $perSet[] = $this->computeMetrics(
                [$setKey],
                $gitCommit,
                $setMeta,
                $setSourceCardCounts,
                $setRows,
                $cardRows,
                $variantRows,
                $externalIdRows,
                $setCardCountChecks,
                $variantHasSourceImage,
                $firstEditionFindings,
                $cardKeyToSetKey,
                perSet: true,
            );
        }

        $report = ['global' => $global, 'per_set' => $perSet];

        $this->printGlobalReport($global);
        $this->printPerSetSummary($perSet);
        file_put_contents("{$outDir}/validation_report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("CSVs + validation_report.json written to: {$outDir}");

        if (! $this->option('apply')) {
            $this->comment('Dry-run only — nothing was written to the database. Pass --apply to import (runs on PASS or PASS_WITH_WARNINGS, refused on FAIL).');

            return self::SUCCESS;
        }

        if ($global['status'] === 'FAIL') {
            $this->error('Validation FAILed — refusing to --apply. Fix the issues above (or re-run without --apply to just inspect the CSVs) first.');

            return self::FAILURE;
        }

        $this->info("Validation {$global['status']} — running cardora:binder-import-v2...");
        $exit = $this->call('cardora:binder-import-v2', ['--path' => $outDir]);

        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function toSetKey(string $tcgdexSetId): string
    {
        return 'pokemon-' . strtolower($tcgdexSetId);
    }

    /** @return array{hash: string, short: string, dirty: bool} */
    private function currentGitCommit(): array
    {
        // base_path() (backend/) is inside the repo, so -C here resolves the
        // same repo-wide HEAD as the repo root would — but pathspecs below
        // must then be relative to backend/, not to the repo root.
        $repoDir = escapeshellarg(base_path());
        $hash = trim((string) shell_exec("git -C {$repoDir} rev-parse HEAD 2>&1"));
        $short = trim((string) shell_exec("git -C {$repoDir} rev-parse --short HEAD 2>&1"));
        $dirty = trim((string) shell_exec("git -C {$repoDir} status --porcelain -- app/Console/Commands/ImportTcgdexPokemon.php 2>&1")) !== '';

        return [
            'hash' => str_starts_with($hash, 'fatal') ? 'unknown (not a git checkout?)' : $hash,
            'short' => str_starts_with($short, 'fatal') ? 'unknown' : $short,
            'dirty' => $dirty,
        ];
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
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $isLastAttempt = $attempt === $maxAttempts;

            try {
                $response = Http::timeout(20)->get($url);
            } catch (\Throwable $e) {
                $this->apiRequestsFailed++;
                if (! $isLastAttempt) {
                    $this->apiRequestsRetried++;
                    usleep(300_000);

                    continue;
                }
                $this->warn("  fetch failed: {$url} ({$e->getMessage()})");

                return null;
            }

            if ($response->successful()) {
                return $response->json();
            }

            $this->apiRequestsFailed++;
            if (! $isLastAttempt) {
                $this->apiRequestsRetried++;
                usleep(300_000);

                continue;
            }
            $this->warn("  fetch failed: {$url} (HTTP {$response->status()})");

            return null;
        }

        return null; // unreachable
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
     * Computes the same metric set for an arbitrary scope of set_keys — the
     * global report passes every set_key in scope, a per-set report passes
     * exactly one. Everything is derived by filtering the already-collected
     * flat rows down to that scope, so global and per-set numbers can never
     * drift apart from using different logic.
     *
     * @param array<int,string> $scopeSetKeys
     * @param array{hash:string,short:string,dirty:bool} $gitCommit
     * @param array<string,array{set_id:string,set_name:string}> $setMeta
     * @param array<string,int> $setSourceCardCounts
     * @param array<int,array<string,mixed>> $setRows
     * @param array<int,array<string,mixed>> $cardRows
     * @param array<int,array<string,mixed>> $variantRows
     * @param array<int,array<string,mixed>> $externalIdRows
     * @param array<int,array<string,mixed>> $setCardCountChecks
     * @param array<string,bool> $variantHasSourceImage
     * @param array<int,array<string,mixed>> $firstEditionFindings
     * @param array<string,string> $cardKeyToSetKey
     */
    private function computeMetrics(
        array $scopeSetKeys,
        array $gitCommit,
        array $setMeta,
        array $setSourceCardCounts,
        array $setRows,
        array $cardRows,
        array $variantRows,
        array $externalIdRows,
        array $setCardCountChecks,
        array $variantHasSourceImage,
        array $firstEditionFindings,
        array $cardKeyToSetKey,
        bool $perSet = false,
    ): array {
        $scope = array_flip($scopeSetKeys);

        $setsScoped = array_values(array_filter($setRows, fn ($s) => isset($scope[$s['set_key']])));
        $cardsScoped = array_values(array_filter($cardRows, fn ($c) => isset($scope[$c['set_key']])));
        $variantsScoped = array_values(array_filter($variantRows, fn ($v) => isset($scope[$cardKeyToSetKey[$v['card_key']] ?? null])));
        $externalIdsScoped = array_values(array_filter($externalIdRows, function ($e) use ($scope, $cardKeyToSetKey) {
            return match ($e['entity_type']) {
                'set' => isset($scope[$e['entity_key']]),
                'card' => isset($scope[$cardKeyToSetKey[$e['entity_key']] ?? null]),
                default => false,
            };
        }));
        $countChecksScoped = array_values(array_filter($setCardCountChecks, fn ($c) => isset($scope[$c['set_key']])));
        $firstEdScoped = array_values(array_filter($firstEditionFindings, fn ($f) => isset($scope[$f['set_key']])));
        $failuresScoped = array_values(array_filter($this->failures, fn ($f) => isset($scope[$this->toSetKey($f['set_id'])])));

        $setKeysGenerated = array_column($setsScoped, 'set_key');
        $cardKeysGenerated = array_column($cardsScoped, 'card_key');
        $variantKeysGenerated = array_column($variantsScoped, 'variant_key');

        $duplicateSetKeys = $this->duplicates($setKeysGenerated);
        $duplicateCardKeys = $this->duplicates($cardKeysGenerated);
        $duplicateVariantKeys = $this->duplicates($variantKeysGenerated);

        $tcgdexCardExternalIds = array_column(
            array_filter($externalIdsScoped, fn ($e) => $e['entity_type'] === 'card' && $e['provider'] === 'tcgdex'),
            'external_id',
        );
        $duplicateSourceCardIds = $this->duplicates($tcgdexCardExternalIds);

        $setKeySet = array_flip($setKeysGenerated);
        $orphanCards = array_values(array_filter($cardsScoped, fn ($c) => ! isset($setKeySet[$c['set_key']])));

        $cardKeySet = array_flip($cardKeysGenerated);
        $orphanVariants = array_values(array_filter($variantsScoped, fn ($v) => ! isset($cardKeySet[$v['card_key']])));

        // Split by whose fault it is: TCGdex simply not having the image yet
        // (upstream, doesn't block — TCGdex's own docs say a missing `image`
        // means "not added to their DB yet") vs. TCGdex giving us an image
        // but our own CSV row ending up empty/malformed (our bug, blocks).
        $upstreamMissingImages = [];
        $importerMissingImages = [];
        foreach ($variantsScoped as $v) {
            $hadSource = $variantHasSourceImage[$v['variant_key']] ?? false;
            $urlLooksValid = str_starts_with((string) $v['image_large'], 'http');
            if (! $hadSource && ! $urlLooksValid) {
                $upstreamMissingImages[] = $v['variant_key'];
            } elseif ($hadSource && ! $urlLooksValid) {
                $importerMissingImages[] = $v['variant_key'];
            }
        }

        $totalsInverted = array_values(array_filter($setsScoped, function ($s) {
            return $s['base_total'] !== '' && $s['numbered_total'] !== '' && (int) $s['base_total'] > (int) $s['numbered_total'];
        }));

        $countMismatches = array_values(array_filter(
            $countChecksScoped,
            fn ($c) => $c['api_card_count_total'] !== $c['cards_array_length'],
        ));

        $ambiguousFirstEdition = array_values(array_filter($firstEdScoped, fn ($f) => $f['ambiguous']));

        $setsFetchFailed = count(array_filter($failuresScoped, fn ($f) => $f['type'] === 'set'));
        $cardsFetchFailed = count(array_filter($failuresScoped, fn ($f) => $f['type'] === 'card'));

        $sourceCards = array_sum(array_intersect_key($setSourceCardCounts, $scope));

        $failCounts = [
            'sets_missing' => max(count($scopeSetKeys) - count($setsScoped), 0),
            'cards_missing' => max($sourceCards - count($cardsScoped), 0),
            'duplicate_set_keys' => count($duplicateSetKeys),
            'duplicate_card_keys' => count($duplicateCardKeys),
            'duplicate_variant_keys' => count($duplicateVariantKeys),
            'duplicate_source_card_ids' => count($duplicateSourceCardIds),
            'orphan_cards' => count($orphanCards),
            'orphan_variants' => count($orphanVariants),
            'totals_inverted' => count($totalsInverted),
            'count_mismatches' => count($countMismatches),
            'importer_missing_images' => count($importerMissingImages),
            // Any permanent fetch failure means we can't be sure this scope's
            // catalog is actually complete — this must FAIL, never just warn.
            'sets_fetch_failed' => $setsFetchFailed,
            'cards_fetch_failed' => $cardsFetchFailed,
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

        $result = [
            'status' => $status,
            'source_sets' => count($scopeSetKeys),
            'fetched_sets' => count($setsScoped),
            'generated_sets' => count($setsScoped),
            'source_cards' => $sourceCards,
            'fetched_cards' => count($cardsScoped),
            'generated_cards' => count($cardsScoped),
            'generated_variants' => count($variantsScoped),
            'duplicate_source_card_ids' => $duplicateSourceCardIds,
            'duplicate_variant_keys' => $duplicateVariantKeys,
            'duplicate_set_keys' => $duplicateSetKeys,
            'duplicate_card_keys' => $duplicateCardKeys,
            'orphan_cards' => array_column($orphanCards, 'card_key'),
            'orphan_variants' => array_column($orphanVariants, 'variant_key'),
            'count_mismatches' => $countMismatches,
            'totals_inverted' => array_column($totalsInverted, 'set_key'),
            'upstream_missing_images' => $upstreamMissingImages,
            'importer_missing_images' => $importerMissingImages,
            'ambiguous_first_edition_mappings' => array_map(fn ($f) => [
                'set_id' => $f['set_id'],
                'card_id' => $f['card_id'],
                'card_name' => $f['card_name'],
                'raw_variant_flags' => $f['raw_variant_flags'],
                'variants_detailed' => $f['variants_detailed'],
                'reason' => $f['reason'],
            ], $ambiguousFirstEdition),
            'sets_fetch_failed' => $setsFetchFailed,
            'cards_fetch_failed' => $cardsFetchFailed,
            'fetch_failures' => $failuresScoped,
        ];

        if ($perSet) {
            $setKey = $scopeSetKeys[0];
            $result = array_merge([
                'set_key' => $setKey,
                'set_id' => $setMeta[$setKey]['set_id'] ?? null,
                'set_name' => $setMeta[$setKey]['set_name'] ?? null,
            ], $result);
        } else {
            $result = array_merge([
                'importer_git_commit' => $gitCommit['hash'],
                'importer_git_commit_short' => $gitCommit['short'],
                'importer_working_tree_dirty' => $gitCommit['dirty'],
                'api_requests_failed' => $this->apiRequestsFailed,
                'api_requests_retried' => $this->apiRequestsRetried,
            ], $result);
        }

        return $result;
    }

    private function duplicates(array $values): array
    {
        $counts = array_count_values($values);

        return array_keys(array_filter($counts, fn ($n) => $n > 1));
    }

    private function printGlobalReport(array $g): void
    {
        $this->newLine();
        $this->info("=== Global validation report (importer {$g['importer_git_commit_short']}) ===");
        $this->table(['Metric', 'Value'], [
            ['api_requests_failed', $g['api_requests_failed']],
            ['api_requests_retried', $g['api_requests_retried']],
            ['sets_fetch_failed', $g['sets_fetch_failed']],
            ['cards_fetch_failed', $g['cards_fetch_failed']],
            ['source_sets', $g['source_sets']],
            ['fetched_sets', $g['fetched_sets']],
            ['generated_sets', $g['generated_sets']],
            ['source_cards', $g['source_cards']],
            ['fetched_cards', $g['fetched_cards']],
            ['generated_cards', $g['generated_cards']],
            ['generated_variants', $g['generated_variants']],
            ['duplicate_source_card_ids', count($g['duplicate_source_card_ids'])],
            ['duplicate_variant_keys', count($g['duplicate_variant_keys'])],
            ['orphan_cards', count($g['orphan_cards'])],
            ['orphan_variants', count($g['orphan_variants'])],
            ['count_mismatches', count($g['count_mismatches'])],
            ['totals_inverted', count($g['totals_inverted'])],
            ['upstream_missing_images (WARNING)', count($g['upstream_missing_images'])],
            ['importer_missing_images (FAIL)', count($g['importer_missing_images'])],
            ['ambiguous_first_edition_mappings (WARNING)', count($g['ambiguous_first_edition_mappings'])],
            ['STATUS', $g['status']],
        ]);

        $listKeys = ['duplicate_source_card_ids', 'duplicate_variant_keys', 'orphan_cards', 'orphan_variants', 'totals_inverted', 'importer_missing_images'];
        foreach ($listKeys as $key) {
            if ($g[$key] !== []) {
                $this->warn(ucfirst(str_replace('_', ' ', $key)) . ': ' . implode(', ', array_slice($g[$key], 0, 20)));
            }
        }
        foreach ($g['count_mismatches'] as $m) {
            $this->warn("  set {$m['set_key']}: API cardCount.total={$m['api_card_count_total']} but fetched {$m['cards_array_length']} cards");
        }
        foreach ($g['ambiguous_first_edition_mappings'] as $f) {
            $this->warn(sprintf('  ambiguous first-edition: set=%s card=%s (%s) — %s | raw flags: %s', $f['set_id'], $f['card_id'], $f['card_name'], $f['reason'], json_encode($f['raw_variant_flags'])));
        }
        foreach ($g['fetch_failures'] as $f) {
            $this->warn("  fetch failure [{$f['type']}] set={$f['set_id']} card=" . ($f['card_id'] ?? '-') . ": {$f['reason']}");
        }
    }

    /** @param array<int,array<string,mixed>> $perSet */
    private function printPerSetSummary(array $perSet): void
    {
        $this->newLine();
        $this->info('=== Per-set summary (' . count($perSet) . ' sets; full detail in validation_report.json) ===');

        $counts = array_count_values(array_column($perSet, 'status'));
        $this->line(sprintf(
            'PASS: %d | PASS_WITH_WARNINGS: %d | FAIL: %d',
            $counts['PASS'] ?? 0,
            $counts['PASS_WITH_WARNINGS'] ?? 0,
            $counts['FAIL'] ?? 0,
        ));

        $notPassing = array_values(array_filter($perSet, fn ($s) => $s['status'] !== 'PASS'));
        if ($notPassing === []) {
            $this->info('Every set is a clean PASS.');

            return;
        }

        $this->table(
            ['set_id', 'set_name', 'cards', 'variants', 'status'],
            array_map(fn ($s) => [$s['set_id'], $s['set_name'], "{$s['generated_cards']}/{$s['source_cards']}", $s['generated_variants'], $s['status']], $notPassing),
        );
    }
}
