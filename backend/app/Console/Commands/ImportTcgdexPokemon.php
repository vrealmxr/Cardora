<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fetches PHYSICAL Pokémon TCG catalog data from TCGdex (api.tcgdex.net,
 * MIT-licensed, no API key) and writes it out as the same 5-CSV format
 * cardora:binder-import-v2 consumes — it does NOT write to the database
 * itself. Pricing/marketplace fields (Cardmarket, TCGplayer) are read from
 * TCGdex but deliberately NOT written anywhere: TCGdex's own docs note the
 * printing→marketplace-listing mapping isn't fully reliable yet, and
 * pricing is planned as a separate, later market-sync pass, not part of
 * the canonical catalog import.
 *
 * Any set whose `serie.id` is "tcgp" is Pokémon TCG Pocket — the digital
 * mobile game, not a physical card — and is excluded entirely (no Set/Card/
 * Variant rows), but counted under excluded_digital_sets/excluded_digital_cards
 * so the exclusion is visible, not silent. A separate pokemon-pocket
 * importer can reuse this same TCGdex source later; this command stays
 * physical-only.
 *
 * Dry-run by default: always generates the CSVs + a validation report
 * (global + per-set). Pass --apply to additionally hand the generated
 * directory to cardora:binder-import-v2 (the only thing that ever writes
 * to the DB), and only if the report is PASS or PASS_WITH_WARNINGS — never
 * on FAIL, which includes any transport fetch failure or genuine upstream
 * data gap still unresolved after retries (we can't know the catalog is
 * complete otherwise).
 */
class ImportTcgdexPokemon extends Command
{
    protected $signature = 'cardora:tcgdex-import-pokemon
        {--set=* : TCGdex set id(s) to fetch, e.g. sv03.5. Omit to fetch every English Pokémon set.}
        {--out= : Directory to write games/sets/cards/variants/external_ids CSVs into (default: storage/app/tcgdex-pokemon)}
        {--apply : Also run cardora:binder-import-v2 on the generated CSVs. Only runs on PASS or PASS_WITH_WARNINGS. Without this flag nothing touches the database.}
        {--source=local : "local" (default, a pinned cards-database git-commit snapshot — no network dependency, use for full rebuilds) or "api" (live api.tcgdex.net calls — use for incremental/update syncs)}
        {--snapshot= : Path to a snapshot dir under storage/app/tcgdex-snapshots (default: the only one present, if exactly one exists)}
        {--delay-ms=80 : Delay between TCGdex card-detail requests in --source=api mode, in milliseconds (politeness against the public API; unused in local mode)}
        {--refresh-cache : --source=api only: ignore the persistent on-disk cache and re-fetch everything (results still get cached)}';

    protected $description = 'Fetch physical Pokémon TCG catalog data from TCGdex and generate catalog v2 import CSVs, with a global + per-set dry-run validation report';

    private const BASE_URL = 'https://api.tcgdex.net/v2/en';

    /**
     * finish flag => [variant_name, variant_type, sort_order]. 'firstEdition'
     * is deliberately NOT here — it's an edition, handled as a modifier on
     * top of these finishes (see processCard()), not a finish itself.
     */
    private const VARIANT_LABELS = [
        'normal' => ['Normal', 'normal', 1],
        'reverse' => ['Reverse Holo', 'reverse_holo', 2],
        'holo' => ['Holo', 'holo', 3],
        'wPromo' => ['W Promotional', 'w_promo', 5],
    ];

    /**
     * set_id => reason. A structural/categorical decision (which sets need
     * a dedicated importer later), not card DATA — unlike the supplemental
     * card lists, which live in database/data/tcgdex-supplemental/ and are
     * never hardcoded here. "jumbo" (oversized physical cards) has its own
     * numbering/product model that doesn't fit Game→Set→Card→Variant
     * cleanly (many are oversized reprints of existing cards), so TCGdex's
     * cardCount=160 isn't treated as canonical truth for it.
     */
    private const EXCLUDED_SPECIAL_FORMAT_SETS = [
        'jumbo' => 'oversized_physical_format_requires_dedicated_catalog',
    ];

    private const SUPPLEMENTAL_DIR = 'database/data/tcgdex-supplemental';

    private array $failures = []; // ['type'=>'set'|'card','set_id'=>..,'card_id'=>?,'reason'=>..] — items still unresolved after the retry pass
    private int $apiRequestsFailed = 0; // failed HTTP attempts, including ones later retried successfully
    private int $apiRequestsRetried = 0; // retry attempts issued (attempt #2, #3, ... after a failed attempt)
    private int $cacheHits = 0;

    private array $setRows = [];
    private array $cardRows = [];
    private array $variantRows = [];
    private array $externalIdRows = [];
    private array $setCardCountChecks = [];
    private array $firstEditionFindings = [];
    private array $variantHasSourceImage = []; // variant_key => bool
    private array $setSourceCardCounts = []; // set_key => count(cards) TCGdex's set listing said this set has
    private array $setMeta = []; // set_key => ['set_id'=>.., 'set_name'=>..], incl. excluded/failed sets
    private array $excludedDigitalSets = []; // TCG Pocket (serie.id=tcgp) sets, kept out of the physical catalog
    private int $excludedDigitalCardCount = 0;
    private array $excludedSpecialFormatSets = []; // e.g. jumbo — real physical cards, but needs a dedicated importer later
    private array $upstreamMissingCardData = []; // set fetch succeeded but returned 0 cards despite a non-zero cardCount, AND no supplemental data covers it
    private array $expectedUniqueVsNumberedDifferences = []; // set returned SOME cards, just fewer/more than cardCount.total — normal for kits/special products, not a failure
    private array $supplementalCardsBySet = []; // set_id => [card rows from supplemental_cards.csv]
    private array $supplementalSetOverrides = []; // set_id => ['set_type_override'=>..., ...provenance]

    private string $sourceMode = 'api';
    private ?array $snapshotManifest = null;
    private array $localSetsById = [];
    private array $localCardsById = [];

    public function handle(): int
    {
        $outDir = rtrim((string) ($this->option('out') ?: storage_path('app/tcgdex-pokemon')), '/');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");

            return self::FAILURE;
        }

        $this->sourceMode = $this->option('source');
        if (! in_array($this->sourceMode, ['local', 'api'], true)) {
            $this->error('--source must be "local" or "api"');

            return self::FAILURE;
        }

        $gitCommit = $this->currentGitCommit();
        $this->info("Importer version: {$gitCommit['hash']}" . ($gitCommit['dirty'] ? ' (dirty working tree)' : ''));

        if ($this->sourceMode === 'local') {
            if (! $this->loadSnapshot()) {
                return self::FAILURE;
            }
            $this->info(sprintf(
                'Source: local snapshot — %s @ %s (%s)',
                $this->snapshotManifest['source_repo'] ?? '?',
                $this->snapshotManifest['commit_short'] ?? '?',
                $this->snapshotManifest['commit_date'] ?? '?',
            ));
        } else {
            $this->info('Source: live api.tcgdex.net');
        }

        $this->loadSupplementalData();

        $this->info('Fetching set list...');
        $allSets = $this->fetchSetsList();
        if ($allSets === null) {
            $this->error('Could not load the TCGdex set list — aborting.');

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

        foreach ($setsToFetch as $i => $setBrief) {
            $this->line(sprintf('[%d/%d] Set %s (%s)', $i + 1, count($setsToFetch), $setBrief['id'], $setBrief['name'] ?? '?'));
            $this->processSet($setBrief['id'], $setBrief['name'] ?? $setBrief['id']);
        }

        $this->retryUnresolvedFailures();

        $this->writeCsvs($outDir, $this->setRows, $this->cardRows, $this->variantRows, $this->externalIdRows);

        $unresolvedSourceGaps = $this->buildUnresolvedSourceGaps();

        $cardKeyToSetKey = array_column($this->cardRows, 'set_key', 'card_key');
        $physicalSetKeys = array_keys($this->setMeta); // excluded (tcgp) sets never get added to setMeta

        $global = $this->computeMetrics($physicalSetKeys, $gitCommit, $cardKeyToSetKey, $unresolvedSourceGaps);

        $perSet = [];
        foreach ($physicalSetKeys as $setKey) {
            $perSet[] = $this->computeMetrics([$setKey], $gitCommit, $cardKeyToSetKey, $unresolvedSourceGaps, perSet: true);
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

    private function processSet(string $setId, string $setBriefName): void
    {
        $setKey = $this->toSetKey($setId);

        $set = $this->fetchSet($setId);
        if ($set === null) {
            $reason = $this->sourceMode === 'local' ? 'not found in local snapshot' : 'fetch failed';
            $this->failures[] = ['type' => 'set', 'set_id' => $setId, 'card_id' => null, 'reason' => $reason];

            return;
        }

        $cardBriefs = $set['cards'] ?? [];

        // Pokémon TCG Pocket is a separate digital-only game (no physical
        // print, no finishes) — excluded from the physical catalog, but
        // recorded so the exclusion is visible rather than silent.
        if (($set['serie']['id'] ?? null) === 'tcgp') {
            $this->excludedDigitalSets[] = [
                'set_id' => $setId,
                'set_name' => $set['name'] ?? $setBriefName,
                'card_count' => count($cardBriefs),
                'reason' => 'serie.id=tcgp (Pokémon TCG Pocket — digital, not physical)',
            ];
            $this->excludedDigitalCardCount += count($cardBriefs);

            return;
        }

        // Real physical cards, but a numbering/product model (oversized
        // reprints of existing cards) that doesn't fit this importer —
        // needs its own normalization later, not TCGdex's cardCount taken
        // at face value.
        if (isset(self::EXCLUDED_SPECIAL_FORMAT_SETS[$setId])) {
            $this->excludedSpecialFormatSets[] = [
                'set_id' => $setId,
                'set_name' => $set['name'] ?? $setBriefName,
                'reason' => self::EXCLUDED_SPECIAL_FORMAT_SETS[$setId],
            ];

            return;
        }

        $setOverride = $this->supplementalSetOverrides[$setId] ?? null;

        $this->setMeta[$setKey] = ['set_id' => $setId, 'set_name' => $set['name'] ?? $setBriefName];
        $this->setRows[] = [
            'game_slug' => 'pokemon',
            'set_key' => $setKey,
            'set_name' => $set['name'] ?? $setId,
            'abbreviation' => $set['abbreviation']['official'] ?? '',
            'set_code' => $setId,
            'set_type' => $setOverride['set_type_override'] ?? 'main',
            'language' => 'EN',
            'region' => '',
            'released_at' => $set['releaseDate'] ?? '',
            'base_total' => $set['cardCount']['official'] ?? '',
            'numbered_total' => $set['cardCount']['total'] ?? '',
            'source' => 'tcgdex',
            'source_set_id' => $setId,
            'source_url' => self::BASE_URL . "/sets/{$setId}",
        ];
        $this->externalIdRows[] = [
            'entity_type' => 'set',
            'entity_key' => $setKey,
            'provider' => 'tcgdex',
            'external_id' => $setId,
            'external_type' => 'set_id',
            'external_url' => '',
        ];

        // If TCGdex itself has zero cards for this set, fall back to hand-
        // researched supplemental data when we have it for this specific
        // set_id — tracked separately (primary vs supplemental) rather than
        // silently merged, so the report always shows where each card's
        // data actually came from.
        $usingSupplemental = false;
        if ($cardBriefs === [] && isset($this->supplementalCardsBySet[$setId])) {
            $usingSupplemental = true;
            foreach ($this->supplementalCardsBySet[$setId] as $supplementalCard) {
                $this->processSupplementalCard($setId, $setKey, $supplementalCard);
            }
            $this->setSourceCardCounts[$setKey] = count($this->supplementalCardsBySet[$setId]);
        } else {
            $this->setSourceCardCounts[$setKey] = count($cardBriefs);
        }

        // Three distinct meanings for "the numbers don't match", not one
        // generic count_mismatch: an empty cards array despite a non-zero
        // cardCount is a genuine upstream gap (FAIL, needs a second source
        // — unless supplemental data just resolved it) — a non-zero-but-
        // different count is normal for kits/special products where
        // "numbered positions" != "unique canonical cards" (warning only,
        // never auto-FAIL).
        $apiTotal = $set['cardCount']['total'] ?? null;
        if (! $usingSupplemental && $apiTotal !== null && count($cardBriefs) !== $apiTotal) {
            $entry = ['set_id' => $setId, 'set_key' => $setKey, 'api_card_count_total' => $apiTotal, 'cards_array_length' => count($cardBriefs)];
            if (count($cardBriefs) === 0) {
                $this->upstreamMissingCardData[] = $entry;
            } else {
                $this->expectedUniqueVsNumberedDifferences[] = $entry;
            }
        }

        foreach ($cardBriefs as $cardBrief) {
            if ($this->sourceMode === 'api') {
                usleep(((int) $this->option('delay-ms')) * 1000);
            }
            $this->processCard($setId, $setKey, $cardBrief['id']);
        }
    }

    private function processCard(string $setId, string $setKey, string $cardId): void
    {
        $card = $this->fetchCard($cardId);
        if ($card === null) {
            $reason = $this->sourceMode === 'local' ? 'not found in local snapshot' : 'fetch failed';
            $this->failures[] = ['type' => 'card', 'set_id' => $setId, 'card_id' => $cardId, 'reason' => $reason];

            return;
        }

        $cardKey = 'pokemon-' . strtolower($cardId);
        $this->cardRows[] = [
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
        $this->externalIdRows[] = [
            'entity_type' => 'card',
            'entity_key' => $cardKey,
            'provider' => 'tcgdex',
            'external_id' => $cardId,
            'external_type' => 'card_id',
            'external_url' => '',
        ];

        // 'firstEdition' is an EDITION, not a finish — it modifies a finish
        // ("Holo, 1st Edition" vs "Holo, Unlimited"), it isn't a peer option
        // alongside normal/reverse/holo. So finishes and edition are
        // resolved separately, then combined.
        $flags = $card['variants'] ?? [];
        $trueFinishes = array_keys(array_filter(array_intersect_key($flags, self::VARIANT_LABELS)));
        if ($trueFinishes === []) {
            $trueFinishes = ['normal']; // schema requires >=1 variant per card
        }
        $hasFirstEdition = ($flags['firstEdition'] ?? false) === true;

        // variants_detailed is per TCGdex's own docs not fully clean
        // (duplicate-looking entries for the same real printing), but an
        // entry's (type, "1st-edition" in stamp) pair is the only signal
        // that ties the edition to a SPECIFIC finish, so it's used only for
        // that yes/no check, never for row counts.
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
            $this->addVariantRow($cardKey, $variantType, $variantName, $sortOrder, $card, $baseImage);

            if (! $hasFirstEdition) {
                continue;
            }

            $confirmedByStamp = isset($stampedFinishes[$finish]);
            if (! $confirmedByStamp && count($trueFinishes) > 1) {
                // >1 finish exists and nothing ties the 1st-edition stamp to
                // *this* one specifically — don't guess.
                $skippedUnresolvedFinishes[] = $finish;

                continue;
            }

            $this->addVariantRow($cardKey, "{$variantType}_1st_edition", "{$variantName} (1st Edition)", $sortOrder + 10, $card, $baseImage);
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
            $this->firstEditionFindings[] = [
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
                'ambiguous' => $inferredWithoutStampConfirmation !== [] || $skippedUnresolvedFinishes !== [],
            ];
        }
    }

    /**
     * Builds Card/Variant/ExternalId rows from a hand-researched
     * supplemental_cards.csv row instead of a TCGdex API/snapshot response.
     * Deliberately does NOT run the finish/first-edition detection
     * processCard() does — we have no variants_detailed for these, so we
     * generate exactly one "Normal" variant per card rather than guess at
     * finishes that were never confirmed. The external_id provider is the
     * supplemental row's own source_provider (e.g. "bulbapedia"), never
     * "tcgdex", so it's traceable which cards came from where.
     *
     * @param array<string,string> $row
     */
    private function processSupplementalCard(string $setId, string $setKey, array $row): void
    {
        $cardKey = 'pokemon-' . strtolower($row['card_id']);

        $this->cardRows[] = [
            'game_slug' => 'pokemon',
            'set_key' => $setKey,
            'card_key' => $cardKey,
            'card_name' => $row['card_name'],
            'clean_name' => $row['card_name'],
            'collector_number' => $row['collector_number'],
            'card_type' => $row['card_type'] ?: '',
            'is_promo' => 'FALSE',
            'is_token' => 'FALSE',
            'language' => 'EN',
        ];
        $this->externalIdRows[] = [
            'entity_type' => 'card',
            'entity_key' => $cardKey,
            'provider' => $row['source_provider'],
            'external_id' => $row['card_id'],
            'external_type' => 'manual_research',
            'external_url' => $row['source_url'],
        ];

        $this->addVariantRow($cardKey, 'normal', 'Normal', 1, ['rarity' => $row['rarity'] ?? '', 'illustrator' => ''], null);
    }

    /**
     * A dedicated pass over ONLY the items still unresolved after getJson()'s
     * own per-request retries — a transient blip during a long run (rate
     * limiting, a dropped connection) shouldn't force a full re-run of the
     * whole catalog. Anything still failing after this pass is a real
     * transport_fetch_failure, reported and left for a second source.
     */
    private function retryUnresolvedFailures(): void
    {
        if ($this->failures === [] || $this->sourceMode === 'local') {
            // Local-snapshot lookups are deterministic in-memory reads — if
            // an id wasn't in the snapshot the first time, it won't be the
            // second time either. Nothing to gain from retrying.
            return;
        }

        $pending = $this->failures;
        $this->failures = [];
        $this->info('Retry pass: ' . count($pending) . ' unresolved item(s)...');

        foreach ($pending as $f) {
            usleep(1_000_000); // more generous pause than the main pass — give transient issues time to clear
            if ($f['type'] === 'set') {
                $this->processSet($f['set_id'], $f['set_id']);
            } else {
                $this->processCard($f['set_id'], $this->toSetKey($f['set_id']), $f['card_id']);
            }
        }

        $stillFailing = count($this->failures);
        $this->info('Retry pass resolved ' . (count($pending) - $stillFailing) . '/' . count($pending) . ' — ' . $stillFailing . ' still unresolved.');
    }

    /** @return array<int,array<string,mixed>> */
    private function buildUnresolvedSourceGaps(): array
    {
        $gaps = [];
        foreach ($this->upstreamMissingCardData as $g) {
            $gaps[] = [
                'category' => 'upstream_missing_card_data',
                'set_id' => $g['set_id'],
                'card_id' => null,
                'reason' => "TCGdex set metadata says {$g['api_card_count_total']} cards but returned an empty cards list",
            ];
        }
        foreach ($this->failures as $f) {
            $gaps[] = [
                'category' => 'transport_fetch_failure',
                'set_id' => $f['set_id'],
                'card_id' => $f['card_id'],
                'reason' => $f['reason'],
            ];
        }

        return $gaps;
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
     * Loads a pinned cards-database snapshot: sets.json/cards.json compiled
     * locally (bun run compile, in the cards-database repo's server/) from
     * an exact git commit, matching the live API's response shape field for
     * field. --snapshot picks the dir explicitly; otherwise this requires
     * there to be exactly one under storage/app/tcgdex-snapshots so a stale
     * or ambiguous default can never be picked silently.
     */
    /**
     * Loads database/data/tcgdex-supplemental/{supplemental_sets,supplemental_cards}.csv
     * — hand-researched fills for specific sets where TCGdex has zero card
     * data, each row carrying its own provenance (source, url, retrieved
     * date, a second cross-check source). Never fabricated here in code;
     * a set with no rows in these files that's still missing TCGdex data
     * stays an unresolved upstream_missing_card_data gap.
     */
    private function loadSupplementalData(): void
    {
        $dir = base_path(self::SUPPLEMENTAL_DIR);

        $setsPath = "{$dir}/supplemental_sets.csv";
        if (is_file($setsPath)) {
            foreach ($this->readCsv($setsPath) as $row) {
                $this->supplementalSetOverrides[$row['set_id']] = $row;
            }
        }

        $cardsPath = "{$dir}/supplemental_cards.csv";
        if (is_file($cardsPath)) {
            foreach ($this->readCsv($cardsPath) as $row) {
                $this->supplementalCardsBySet[$row['set_id']][] = $row;
            }
        }

        if ($this->supplementalCardsBySet !== []) {
            $this->info(sprintf(
                'Loaded supplemental data: %d set(s) (%s)',
                count($this->supplementalCardsBySet),
                implode(', ', array_keys($this->supplementalCardsBySet)),
            ));
        }
    }

    /** @return array<int,array<string,string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle) ?: [];
        $rows = [];
        $lineNo = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;
            if (count($row) !== count($header)) {
                // array_combine() throws (not just returns false) on a
                // length mismatch in PHP 8 — most likely an unquoted comma
                // in a free-text column. Skip the row rather than crash the
                // whole import over one malformed CSV line.
                $this->warn("  {$path}:{$lineNo}: column count mismatch (expected " . count($header) . ', got ' . count($row) . '), skipping row');

                continue;
            }
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rows;
    }

    private function loadSnapshot(): bool
    {
        $snapshotDir = $this->option('snapshot');
        if (! $snapshotDir) {
            $root = storage_path('app/tcgdex-snapshots');
            $candidates = is_dir($root) ? array_values(array_filter(glob("{$root}/*"), 'is_dir')) : [];
            if (count($candidates) !== 1) {
                $this->error(sprintf(
                    '--source=local needs --snapshot=<dir>: found %d snapshot(s) under %s (need exactly 1 to pick a default).',
                    count($candidates),
                    $root,
                ));

                return false;
            }
            $snapshotDir = $candidates[0];
        }

        $manifestPath = "{$snapshotDir}/manifest.json";
        $setsPath = "{$snapshotDir}/en/sets.json";
        $cardsPath = "{$snapshotDir}/en/cards.json";
        foreach (['manifest.json' => $manifestPath, 'en/sets.json' => $setsPath, 'en/cards.json' => $cardsPath] as $label => $path) {
            if (! is_file($path)) {
                $this->error("Snapshot missing {$label} at {$path}");

                return false;
            }
        }

        $this->snapshotManifest = json_decode((string) file_get_contents($manifestPath), true);

        $sets = json_decode((string) file_get_contents($setsPath), true);
        foreach ($sets as $set) {
            $this->localSetsById[$set['id']] = $set;
        }

        $cards = json_decode((string) file_get_contents($cardsPath), true);
        foreach ($cards as $card) {
            $this->localCardsById[$card['id']] = $card;
        }

        $this->info(sprintf('Loaded snapshot: %d sets, %d cards from %s', count($this->localSetsById), count($this->localCardsById), $snapshotDir));

        return true;
    }

    /** @return array<int,array<string,mixed>>|null */
    private function fetchSetsList(): ?array
    {
        if ($this->sourceMode === 'local') {
            return array_values($this->localSetsById);
        }

        return $this->getJson(self::BASE_URL . '/sets');
    }

    private function fetchSet(string $id): ?array
    {
        if ($this->sourceMode === 'local') {
            return $this->localSetsById[$id] ?? null;
        }

        return $this->getJson(self::BASE_URL . "/sets/{$id}");
    }

    private function fetchCard(string $id): ?array
    {
        if ($this->sourceMode === 'local') {
            return $this->localCardsById[$id] ?? null;
        }

        return $this->getJson(self::BASE_URL . "/cards/{$id}");
    }

    /** @param array<string,mixed> $card */
    private function addVariantRow(string $cardKey, string $variantType, string $variantName, int $sortOrder, array $card, ?string $baseImage): void
    {
        $variantKey = "{$cardKey}-{$variantType}";
        $this->variantRows[] = [
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
        $this->variantHasSourceImage[$variantKey] = $baseImage !== null;
    }

    /**
     * Persistent disk cache (storage/app/tcgdex-cache) keyed by URL, so
     * re-running the importer (after a code change, or to finish an
     * interrupted run) doesn't re-hit already-successfully-fetched
     * endpoints. Only successful responses are cached. --refresh-cache
     * bypasses reads but still writes fresh results.
     */
    private function getJson(string $url): ?array
    {
        $cacheFile = storage_path('app/tcgdex-cache/' . sha1($url) . '.json');
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
                $response = Http::timeout(20)->get($url);
            } catch (\Throwable $e) {
                $this->apiRequestsFailed++;
                if (! $isLastAttempt) {
                    $this->apiRequestsRetried++;
                    usleep(300_000 * (2 ** ($attempt - 1))); // exponential backoff: 300ms, 600ms, ...


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
                usleep(300_000 * (2 ** ($attempt - 1)));

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
     * global report passes every physical set_key, a per-set report passes
     * exactly one. Everything is derived by filtering the already-collected
     * flat rows down to that scope, so global and per-set numbers can never
     * drift apart from using different logic. Excluded (TCG Pocket) sets
     * never reach here — they're reported separately.
     *
     * @param array<int,string> $scopeSetKeys
     * @param array{hash:string,short:string,dirty:bool} $gitCommit
     * @param array<string,string> $cardKeyToSetKey
     * @param array<int,array<string,mixed>> $unresolvedSourceGaps
     */
    private function computeMetrics(array $scopeSetKeys, array $gitCommit, array $cardKeyToSetKey, array $unresolvedSourceGaps, bool $perSet = false): array
    {
        $scope = array_flip($scopeSetKeys);

        $setsScoped = array_values(array_filter($this->setRows, fn ($s) => isset($scope[$s['set_key']])));
        $cardsScoped = array_values(array_filter($this->cardRows, fn ($c) => isset($scope[$c['set_key']])));
        $variantsScoped = array_values(array_filter($this->variantRows, fn ($v) => isset($scope[$cardKeyToSetKey[$v['card_key']] ?? null])));
        $externalIdsScoped = array_values(array_filter($this->externalIdRows, function ($e) use ($scope, $cardKeyToSetKey) {
            return match ($e['entity_type']) {
                'set' => isset($scope[$e['entity_key']]),
                'card' => isset($scope[$cardKeyToSetKey[$e['entity_key']] ?? null]),
                default => false,
            };
        }));
        $firstEdScoped = array_values(array_filter($this->firstEditionFindings, fn ($f) => isset($scope[$f['set_key']])));
        $failuresScoped = array_values(array_filter($this->failures, fn ($f) => isset($scope[$this->toSetKey($f['set_id'])])));
        $upstreamGapScoped = array_values(array_filter($this->upstreamMissingCardData, fn ($c) => isset($scope[$c['set_key']])));
        $expectedDiffScoped = array_values(array_filter($this->expectedUniqueVsNumberedDifferences, fn ($c) => isset($scope[$c['set_key']])));
        $gapsScoped = array_values(array_filter($unresolvedSourceGaps, fn ($g) => isset($scope[$this->toSetKey($g['set_id'])])));

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

        // Every card's provider (tcgdex = primary, anything else = a
        // database/data/tcgdex-supplemental/ source) so the report always
        // shows where each card's data actually came from.
        $cardProviders = array_column(
            array_filter($externalIdsScoped, fn ($e) => $e['entity_type'] === 'card'),
            'provider',
            'entity_key',
        );
        $primaryCardsScoped = array_values(array_filter($cardsScoped, fn ($c) => ($cardProviders[$c['card_key']] ?? null) === 'tcgdex'));
        $supplementalCardsScoped = array_values(array_filter($cardsScoped, fn ($c) => ($cardProviders[$c['card_key']] ?? null) !== 'tcgdex'));

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
            $hadSource = $this->variantHasSourceImage[$v['variant_key']] ?? false;
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

        $ambiguousFirstEdition = array_values(array_filter($firstEdScoped, fn ($f) => $f['ambiguous']));

        $sourceCards = array_sum(array_intersect_key($this->setSourceCardCounts, $scope));

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
            'importer_missing_images' => count($importerMissingImages),
            // Genuine upstream gaps and unresolved transport failures both
            // mean we can't be sure this scope's catalog is complete —
            // both FAIL. expected_unique_vs_numbered differences do NOT
            // (kits/special products legitimately have fewer/more unique
            // cards than "numbered positions").
            'upstream_missing_card_data' => count($upstreamGapScoped),
            'transport_fetch_failures' => count($failuresScoped),
        ];
        $warningCounts = [
            'upstream_missing_images' => count($upstreamMissingImages),
            'ambiguous_first_edition_mappings' => count($ambiguousFirstEdition),
            'expected_unique_vs_numbered_differences' => count($expectedDiffScoped),
        ];

        $status = match (true) {
            array_sum($failCounts) > 0 => 'FAIL',
            array_sum($warningCounts) > 0 => 'PASS_WITH_WARNINGS',
            default => 'PASS',
        };

        $excludedDigitalSetsScoped = $perSet ? [] : $this->excludedDigitalSets; // exclusion is global-only, not meaningful per physical set
        $excludedSpecialFormatSetsScoped = $perSet ? [] : $this->excludedSpecialFormatSets;

        $result = [
            'status' => $status,
            'source_physical_sets' => count($scopeSetKeys),
            'generated_sets' => count($setsScoped),
            'source_physical_cards' => $sourceCards,
            'primary_source_cards' => count($primaryCardsScoped),
            'supplemental_cards' => count($supplementalCardsScoped),
            'generated_cards' => count($cardsScoped),
            'generated_variants' => count($variantsScoped),
            'duplicate_source_card_ids' => $duplicateSourceCardIds,
            'duplicate_variant_keys' => $duplicateVariantKeys,
            'duplicate_set_keys' => $duplicateSetKeys,
            'duplicate_card_keys' => $duplicateCardKeys,
            'orphan_cards' => array_column($orphanCards, 'card_key'),
            'orphan_variants' => array_column($orphanVariants, 'variant_key'),
            'upstream_missing_card_data' => $upstreamGapScoped,
            'expected_unique_vs_numbered_differences' => $expectedDiffScoped,
            'transport_fetch_failures' => $failuresScoped,
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
            'unresolved_source_gaps' => $gapsScoped,
        ];

        if ($perSet) {
            $setKey = $scopeSetKeys[0];
            $result = array_merge([
                'set_key' => $setKey,
                'set_id' => $this->setMeta[$setKey]['set_id'] ?? null,
                'set_name' => $this->setMeta[$setKey]['set_name'] ?? null,
            ], $result);
        } else {
            $result = array_merge([
                'importer_git_commit' => $gitCommit['hash'],
                'importer_git_commit_short' => $gitCommit['short'],
                'importer_working_tree_dirty' => $gitCommit['dirty'],
                'source_mode' => $this->sourceMode,
                'source_snapshot_repo' => $this->snapshotManifest['source_repo'] ?? null,
                'source_snapshot_commit' => $this->snapshotManifest['commit'] ?? null,
                'source_snapshot_commit_date' => $this->snapshotManifest['commit_date'] ?? null,
                'api_requests_failed' => $this->apiRequestsFailed,
                'api_requests_retried' => $this->apiRequestsRetried,
                'cache_hits' => $this->cacheHits,
                'excluded_digital_sets' => $excludedDigitalSetsScoped,
                'excluded_digital_cards' => $this->excludedDigitalCardCount,
                'excluded_special_format_sets' => $excludedSpecialFormatSetsScoped,
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
        $this->line("source_mode: {$g['source_mode']}" . ($g['source_mode'] === 'local'
            ? " | snapshot: {$g['source_snapshot_repo']} @ {$g['source_snapshot_commit']} ({$g['source_snapshot_commit_date']})"
            : ''));
        $this->table(['Metric', 'Value'], [
            ['api_requests_failed', $g['api_requests_failed']],
            ['api_requests_retried', $g['api_requests_retried']],
            ['cache_hits', $g['cache_hits']],
            ['excluded_digital_sets (TCG Pocket)', count($g['excluded_digital_sets'])],
            ['excluded_digital_cards (TCG Pocket)', $g['excluded_digital_cards']],
            ['excluded_special_format_sets', count($g['excluded_special_format_sets'])],
            ['source_physical_sets', $g['source_physical_sets']],
            ['generated_sets', $g['generated_sets']],
            ['source_physical_cards', $g['source_physical_cards']],
            ['  primary_source_cards', $g['primary_source_cards']],
            ['  supplemental_cards', $g['supplemental_cards']],
            ['generated_cards', $g['generated_cards']],
            ['generated_variants', $g['generated_variants']],
            ['duplicate_source_card_ids', count($g['duplicate_source_card_ids'])],
            ['duplicate_variant_keys', count($g['duplicate_variant_keys'])],
            ['orphan_cards', count($g['orphan_cards'])],
            ['orphan_variants', count($g['orphan_variants'])],
            ['upstream_missing_card_data (FAIL)', count($g['upstream_missing_card_data'])],
            ['expected_unique_vs_numbered_differences (WARNING)', count($g['expected_unique_vs_numbered_differences'])],
            ['transport_fetch_failures (FAIL)', count($g['transport_fetch_failures'])],
            ['totals_inverted', count($g['totals_inverted'])],
            ['upstream_missing_images (WARNING)', count($g['upstream_missing_images'])],
            ['importer_missing_images (FAIL)', count($g['importer_missing_images'])],
            ['ambiguous_first_edition_mappings (WARNING)', count($g['ambiguous_first_edition_mappings'])],
            ['unresolved_source_gaps', count($g['unresolved_source_gaps'])],
            ['STATUS', $g['status']],
        ]);

        $listKeys = ['duplicate_source_card_ids', 'duplicate_variant_keys', 'orphan_cards', 'orphan_variants', 'totals_inverted', 'importer_missing_images'];
        foreach ($listKeys as $key) {
            if ($g[$key] !== []) {
                $this->warn(ucfirst(str_replace('_', ' ', $key)) . ': ' . implode(', ', array_slice($g[$key], 0, 20)));
            }
        }
        foreach ($g['excluded_digital_sets'] as $s) {
            $this->comment("  excluded (TCG Pocket): {$s['set_id']} ({$s['set_name']}) — {$s['card_count']} cards");
        }
        foreach ($g['excluded_special_format_sets'] as $s) {
            $this->comment("  excluded (special format): {$s['set_id']} ({$s['set_name']}) — {$s['reason']}");
        }
        foreach ($g['upstream_missing_card_data'] as $m) {
            $this->error("  upstream_missing_card_data: set {$m['set_id']} — metadata says {$m['api_card_count_total']} cards, API returned 0");
        }
        foreach ($g['expected_unique_vs_numbered_differences'] as $m) {
            $this->comment("  expected_unique_vs_numbered_difference: set {$m['set_id']} — {$m['cards_array_length']} canonical cards vs {$m['api_card_count_total']} numbered positions (not a failure)");
        }
        foreach ($g['ambiguous_first_edition_mappings'] as $f) {
            $this->warn(sprintf('  ambiguous first-edition: set=%s card=%s (%s) — %s | raw flags: %s', $f['set_id'], $f['card_id'], $f['card_name'], $f['reason'], json_encode($f['raw_variant_flags'])));
        }
        foreach ($g['transport_fetch_failures'] as $f) {
            $this->error("  transport_fetch_failure [{$f['type']}] set={$f['set_id']} card=" . ($f['card_id'] ?? '-') . ": {$f['reason']}");
        }
    }

    /** @param array<int,array<string,mixed>> $perSet */
    private function printPerSetSummary(array $perSet): void
    {
        $this->newLine();
        $this->info('=== Per-set summary (' . count($perSet) . ' physical sets; full detail in validation_report.json) ===');

        $counts = array_count_values(array_column($perSet, 'status'));
        $this->line(sprintf(
            'PASS: %d | PASS_WITH_WARNINGS: %d | FAIL: %d',
            $counts['PASS'] ?? 0,
            $counts['PASS_WITH_WARNINGS'] ?? 0,
            $counts['FAIL'] ?? 0,
        ));

        $notPassing = array_values(array_filter($perSet, fn ($s) => $s['status'] !== 'PASS'));
        if ($notPassing === []) {
            $this->info('Every physical set is a clean PASS.');

            return;
        }

        $this->table(
            ['set_id', 'set_name', 'cards', 'variants', 'status'],
            array_map(fn ($s) => [$s['set_id'], $s['set_name'], "{$s['generated_cards']}/{$s['source_physical_cards']}", $s['generated_variants'], $s['status']], $notPassing),
        );
    }
}
