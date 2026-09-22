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
    private const SUPPLEMENTAL_DIR = 'database/data/ygoprodeck-supplemental';

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

    private array $supplementalCardsBySet = []; // set_name => [rows from supplemental_cards.csv]
    private array $supplementalExcludedSpecialFormat = []; // status=excluded_special_format rows, reported not imported
    private array $cardKeyOrigin = []; // card_key => 'primary'|'supplemental', used for the report split
    private int $importerMissingImages = 0; // fallback_card_artwork was expected but the generated URL is empty/malformed
    private array $variantOverrides = []; // rows from supplemental_variant_overrides.csv
    private int $regionSpecificVariants = 0;
    private int $editionSpecificVariants = 0;
    private array $overriddenCardKeys = []; // card_key => true, cards touched by supplemental_variant_overrides.csv (region/edition tagging on an existing variant, no new card/variant created by this alone)
    private array $fullyExcludedSetNames = []; // set_name => true, every supplemental_cards.csv row for this set is excluded_special_format (no "include" rows) -- e.g. a set later found to be a duplicate canonical printing of another set's content
    private array $droppedFullyExcludedSets = []; // set_names actually dropped from setRows because they ended up with zero real cards (reported, never silent)

    private array $supplementalReleases = []; // release_key => row, from supplemental_releases.csv
    private array $supplementalReleaseMemberships = []; // raw rows from supplemental_release_memberships.csv
    private array $releaseRows = [];
    private array $releaseMembershipRows = [];
    private int $releaseMembershipsUnresolved = 0; // csv rows whose (set_name, collector_number) or release_key didn't resolve -- warned, not written

    public function handle(): int
    {
        $outDir = rtrim((string) ($this->option('out') ?: storage_path('app/ygoprodeck-yugioh')), '/');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");

            return self::FAILURE;
        }

        $gitCommit = $this->currentGitCommit();
        $this->info("Importer version: {$gitCommit['hash']}" . ($gitCommit['dirty'] ? ' (dirty working tree)' : ''));

        $this->loadSupplementalData();

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
        $canonicalSetNameByLowercase = []; // strtolower(set_name) => real set_name, for the casing-typo fallback below
        foreach ($allSets as $s) {
            $canonicalSets[$s['set_name']] = $s;
            $canonicalSetNameByLowercase[strtolower($s['set_name'])] = $s['set_name'];
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
                $rawSetName = $cs['set_name'] ?? null;
                $fullSetCode = $cs['set_code'] ?? null;

                // YGOPRODeck's own card_sets entries aren't always cased
                // identically to their cardsets.php registration for the
                // same real set (e.g. "...Promotional Cards" vs
                // "...promotional cards") — fall back to a case-insensitive
                // match rather than treating a pure casing difference as a
                // genuinely unknown set.
                $setName = $rawSetName !== null && isset($canonicalSets[$rawSetName])
                    ? $rawSetName
                    : $canonicalSetNameByLowercase[strtolower((string) $rawSetName)] ?? null;

                if ($setName === null || $fullSetCode === null) {
                    $entry = ['card_name' => $card['name'] ?? '?', 'set_name' => $rawSetName, 'set_code' => $fullSetCode];
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
                        'origin' => 'primary',
                    ];
                }
                $groups[$setKey][$fullSetCode]['rarities'][$rarity] ??= ['region_code' => null, 'edition_code' => null];
            }

            if ($isSkill && $resolvedAny) {
                $this->skillCardsIncluded++;
            }
            if ($isToken && $resolvedAny) {
                $this->physicalTokensIncluded++;
            }
        }

        $this->mergeSupplementalCards($groups, $canonicalSets);
        $this->applyVariantOverrides($groups, $canonicalSetNameByLowercase);

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
                $this->cardKeyOrigin[$cardKey] = $group['origin'] ?? 'primary';

                if ($group['origin'] === 'supplemental') {
                    $this->externalIdRows[] = [
                        'entity_type' => 'card',
                        'entity_key' => $cardKey,
                        'provider' => $group['source_provider'],
                        'external_id' => $fullSetCode,
                        'external_type' => 'manual_research',
                        'external_url' => $group['source_url'],
                    ];
                } elseif (count($setKeysByFullCode[$fullSetCode]) === 1) {
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

                if ($group['image']['status'] === 'fallback_card_artwork' && $group['image']['large'] === '') {
                    $this->importerMissingImages++;
                }

                $sortOrder = 1;
                foreach ($group['rarities'] as $rarity => $dims) {
                    // The uniqueness concept is card + rarity + region_code
                    // + edition_code (+ whatever other real printing
                    // dimension shows up later) — never just card + rarity.
                    // variant_name/variant_type stay the plain rarity name;
                    // region/edition live in their own structured columns,
                    // not smuggled into a display string.
                    $regionCode = $dims['region_code'] ?? null;
                    $editionCode = $dims['edition_code'] ?? null;
                    $variantType = $this->slug($rarity ?: 'unknown');
                    $keySuffix = ($regionCode ? "-{$regionCode}" : '') . ($editionCode ? "-{$editionCode}" : '');
                    $this->variantRows[] = [
                        'card_key' => $cardKey,
                        'variant_key' => "{$cardKey}-{$variantType}{$keySuffix}",
                        'variant_name' => $rarity ?: 'Unknown',
                        'variant_type' => $variantType,
                        'rarity' => $rarity,
                        'region_code' => $regionCode ?? '',
                        'edition_code' => $editionCode ?? '',
                        'artist' => '',
                        'image_small' => $group['image']['small'],
                        'image_large' => $group['image']['large'],
                        'sort_order' => $sortOrder++,
                    ];
                }
            }
        }

        // Drop canonical Set rows for fully-excluded supplemental sets that
        // ended up with zero real cards (see fullyExcludedSetNames above) --
        // must happen before the gap-detection loop below, otherwise a
        // deliberately-emptied set (e.g. a confirmed duplicate canonical
        // printing) would misreport as an unresolved_source_gap (FAIL).
        foreach (array_keys($this->fullyExcludedSetNames) as $setName) {
            $setKey = $this->toSetKey($setName);
            if (($this->setActualCardCounts[$setKey] ?? 0) === 0 && isset($this->setRows[$setKey])) {
                unset($this->setRows[$setKey], $this->setSourceCardCounts[$setKey], $this->setActualCardCounts[$setKey]);
                $this->externalIdRows = array_values(array_filter(
                    $this->externalIdRows,
                    fn ($e) => ! ($e['entity_type'] === 'set' && $e['entity_key'] === $setKey),
                ));
                $this->droppedFullyExcludedSets[] = $setName;
            }
        }

        $this->buildReleases($canonicalSetNameByLowercase);

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
    /**
     * Loads database/data/ygoprodeck-supplemental/supplemental_cards.csv —
     * hand-researched fills for sets where YGOPRODeck has zero card_sets
     * data. Never used automatically; only the exact set_name values
     * present in this file are affected.
     */
    private function loadSupplementalData(): void
    {
        $cardsPath = base_path(self::SUPPLEMENTAL_DIR) . '/supplemental_cards.csv';
        if (is_file($cardsPath)) {
            foreach ($this->readCsv($cardsPath) as $row) {
                $this->supplementalCardsBySet[$row['set_name']][] = $row;
            }
        }

        // A set_name where every supplemental_cards.csv row is
        // excluded_special_format (no "include" rows at all) means this
        // dataset has deliberately decided the set has zero real canonical
        // cards -- e.g. Power of Chaos: Yugi the Destiny Limited Collector's
        // Edition, whose 5 cards turned out to duplicate the sibling
        // "...promotional cards" set already in primary data. Tracked here
        // so handle() can drop the (now legitimately empty) canonical Set
        // row instead of it tripping the unresolved_source_gaps FAIL gate.
        foreach ($this->supplementalCardsBySet as $setName => $rows) {
            if (array_filter($rows, fn ($r) => $r['status'] === 'include') === []) {
                $this->fullyExcludedSetNames[$setName] = true;
            }
        }

        $variantsPath = base_path(self::SUPPLEMENTAL_DIR) . '/supplemental_variant_overrides.csv';
        if (is_file($variantsPath)) {
            $this->variantOverrides = $this->readCsv($variantsPath);
        }

        $releasesPath = base_path(self::SUPPLEMENTAL_DIR) . '/supplemental_releases.csv';
        if (is_file($releasesPath)) {
            foreach ($this->readCsv($releasesPath) as $row) {
                $this->supplementalReleases[$row['release_key']] = $row;
            }
        }

        $releaseMembershipsPath = base_path(self::SUPPLEMENTAL_DIR) . '/supplemental_release_memberships.csv';
        if (is_file($releaseMembershipsPath)) {
            $this->supplementalReleaseMemberships = $this->readCsv($releaseMembershipsPath);
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
                $this->warn("  {$path}:{$lineNo}: column count mismatch, skipping row");

                continue;
            }
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Merges supplemental_cards.csv rows into the primary $groups structure
     * so both flow through the exact same emission/classification logic
     * afterwards. `status=excluded_special_format` rows never enter
     * $groups (no Card/Variant rows) — recorded separately for the report
     * and roadmap instead.
     *
     * @param array<string,array<string,array<string,mixed>>> $groups
     * @param array<string,array<string,mixed>> $canonicalSets
     */
    private function mergeSupplementalCards(array &$groups, array $canonicalSets): void
    {
        foreach ($this->supplementalCardsBySet as $setName => $rows) {
            if (! isset($canonicalSets[$setName])) {
                $this->warn("  supplemental_cards.csv: set_name \"{$setName}\" not found in YGOPRODeck cardsets.php (typo/casing mismatch?) — its rows were NOT merged");

                continue;
            }
            $setKey = $this->toSetKey($setName);

            foreach ($rows as $row) {
                if ($row['status'] === 'excluded_special_format') {
                    $this->supplementalExcludedSpecialFormat[] = [
                        'set_name' => $setName,
                        'card_id' => $row['card_id'],
                        'card_name' => $row['card_name'],
                        'reason' => $row['exclusion_reason'],
                        'source_url' => $row['source_url'],
                    ];

                    continue;
                }

                $fullSetCode = $row['collector_number'];
                if (! isset($groups[$setKey][$fullSetCode])) {
                    $groups[$setKey][$fullSetCode] = [
                        'card_name' => $row['card_name'],
                        'card_type' => $row['card_type'],
                        'ygoprodeck_id' => null,
                        'is_token' => false,
                        'rarities' => [],
                        'image' => ['small' => '', 'large' => '', 'status' => 'no_artwork'],
                        'origin' => 'supplemental',
                        'source_provider' => $row['source_provider'],
                        'source_url' => $row['source_url'],
                    ];
                }
                $groups[$setKey][$fullSetCode]['rarities'][$row['rarity']] ??= ['region_code' => null, 'edition_code' => null];
            }
        }
    }

    /**
     * Applies database/data/ygoprodeck-supplemental/supplemental_variant_overrides.csv
     * — tags region_code/edition_code onto specific (set, collector_number,
     * rarity) variants that already exist in $groups (built by the primary
     * loop and/or mergeSupplementalCards() above), rather than creating new
     * cards. This is where e.g. Yu-Gi-Oh! Tag Force 5 promos' Ultra Rare
     * (North America) vs Super Rare (Europe) split gets tagged — the two
     * rarities were already distinct rows; this just adds the region fact.
     *
     * @param array<string,array<string,array<string,mixed>>> $groups
     * @param array<string,string> $canonicalSetNameByLowercase
     */
    private function applyVariantOverrides(array &$groups, array $canonicalSetNameByLowercase): void
    {
        foreach ($this->variantOverrides as $row) {
            $setName = $canonicalSetNameByLowercase[strtolower($row['set_name'])] ?? $row['set_name'];
            $setKey = $this->toSetKey($setName);
            $fullSetCode = $row['collector_number'];
            $rarity = $row['rarity'];

            if (! isset($groups[$setKey][$fullSetCode]['rarities'][$rarity])) {
                $this->warn("  supplemental_variant_overrides.csv: no existing variant for {$setName} / {$fullSetCode} / \"{$rarity}\" — override NOT applied");

                continue;
            }

            $regionCode = $row['region_code'] !== '' ? $row['region_code'] : null;
            $editionCode = $row['edition_code'] !== '' ? $row['edition_code'] : null;
            $groups[$setKey][$fullSetCode]['rarities'][$rarity] = ['region_code' => $regionCode, 'edition_code' => $editionCode];
            $this->overriddenCardKeys[$this->toCardKey($setKey, $fullSetCode)] = true;

            if ($regionCode !== null) {
                $this->regionSpecificVariants++;
            }
            if ($editionCode !== null) {
                $this->editionSpecificVariants++;
            }
        }
    }

    /**
     * Builds releases.csv / release_memberships.csv from
     * supplemental_releases.csv / supplemental_release_memberships.csv —
     * the additive Release/Card_Release_Membership layer (separate from
     * binder_cards.set_id, which stays the primary/canonical checklist
     * grouping). Expresses "this same physical printing also shipped in
     * another product" (e.g. Kaiba's Collector Box + Yugi & Kaiba Collector
     * Box both containing KACB-EN001) WITHOUT creating a second canonical
     * card — the fix for the KACB/YUCB/PCY duplicate-canonical-card class
     * of bug found during production spot-checks.
     *
     * @param array<string,string> $canonicalSetNameByLowercase
     */
    private function buildReleases(array $canonicalSetNameByLowercase): void
    {
        foreach ($this->supplementalReleases as $releaseKey => $row) {
            $this->releaseRows[$releaseKey] = [
                'game_slug' => 'yugioh',
                'release_key' => $releaseKey,
                'release_name' => $row['release_name'],
                'release_type' => $row['release_type'],
                'region_code' => $row['region_code'],
                'released_at' => $row['released_at'],
                'source_provider' => $row['source_provider'],
                'source_external_id' => $row['source_external_id'],
                'notes' => $row['notes'],
            ];
        }

        $cardKeySet = array_flip(array_column($this->cardRows, 'card_key'));
        $seenPairs = [];

        foreach ($this->supplementalReleaseMemberships as $row) {
            $setName = $canonicalSetNameByLowercase[strtolower($row['set_name'])] ?? $row['set_name'];
            $setKey = $this->toSetKey($setName);
            $cardKey = $this->toCardKey($setKey, $row['collector_number']);
            $releaseKey = $row['release_key'];

            if (! isset($cardKeySet[$cardKey]) || ! isset($this->releaseRows[$releaseKey])) {
                $this->warn("  supplemental_release_memberships.csv: could not resolve card \"{$row['set_name']}\" / {$row['collector_number']} or release \"{$releaseKey}\" — membership NOT created");
                $this->releaseMembershipsUnresolved++;

                continue;
            }

            $pairKey = "{$cardKey}:{$releaseKey}";
            if (isset($seenPairs[$pairKey])) {
                continue; // supplemental data listed the same (card, release) pair twice -- de-duped, not an error
            }
            $seenPairs[$pairKey] = true;

            $this->releaseMembershipRows[] = [
                'card_key' => $cardKey,
                'release_key' => $releaseKey,
                'membership_type' => $row['membership_type'],
                'source_provider' => $row['source_provider'],
                'source_reference' => $row['source_reference'],
                'notes' => $row['notes'],
            ];
        }
    }

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
            'card_key', 'variant_key', 'variant_name', 'variant_type', 'rarity', 'region_code', 'edition_code', 'artist',
            'image_small', 'image_large', 'sort_order',
        ], $this->variantRows);
        $this->writeCsv("{$outDir}/external_ids.csv", [
            'entity_type', 'entity_key', 'provider', 'external_id', 'external_type', 'external_url',
        ], $this->externalIdRows);
        $this->writeCsv("{$outDir}/releases.csv", [
            'game_slug', 'release_key', 'release_name', 'release_type', 'region_code', 'released_at', 'source_provider', 'source_external_id', 'notes',
        ], array_values($this->releaseRows));
        $this->writeCsv("{$outDir}/release_memberships.csv", [
            'card_key', 'release_key', 'membership_type', 'source_provider', 'source_reference', 'notes',
        ], $this->releaseMembershipRows);
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

        $primarySourceCards = count(array_filter($this->cardKeyOrigin, fn ($o) => $o === 'primary'));
        $supplementalCards = count(array_filter($this->cardKeyOrigin, fn ($o) => $o === 'supplemental'));

        // set_keys that received at least one supplemental (status=include) card
        $supplementalSetKeys = [];
        foreach ($this->supplementalCardsBySet as $setName => $rows) {
            if (array_filter($rows, fn ($r) => $r['status'] === 'include') !== []) {
                $supplementalSetKeys[$this->toSetKey($setName)] = true;
            }
        }

        // Disambiguated origin split requested for the TF05 reconciliation:
        // a "primary" set/card needs zero supplemental data at all; a
        // "supplemental_only" set/card exists ONLY because upstream had no
        // card_sets data for it whatsoever (the upstream_missing_card_data
        // gap-fill case, e.g. Adidas Collaboration Card). A card can be
        // fully primary-sourced yet still have a variant_overrides.csv row
        // (TF05) — that's "supplemental_overridden", tracked separately
        // from "supplemental_only" since it creates zero new cards/sets.
        $setsWithPrimaryCard = [];
        $setsWithSupplementalCard = [];
        foreach ($this->cardRows as $c) {
            $origin = $this->cardKeyOrigin[$c['card_key']] ?? 'primary';
            if ($origin === 'primary') {
                $setsWithPrimaryCard[$c['set_key']] = true;
            } else {
                $setsWithSupplementalCard[$c['set_key']] = true;
            }
        }
        $supplementalOnlySetKeys = array_diff_key($setsWithSupplementalCard, $setsWithPrimaryCard);

        // Release/Card_Release_Membership layer (additive, see buildReleases()).
        $releaseKeySet = array_flip(array_column($this->releaseRows, 'release_key'));
        $membershipPairKeys = array_map(fn ($m) => "{$m['card_key']}:{$m['release_key']}", $this->releaseMembershipRows);
        $duplicateReleaseMemberships = $this->duplicates($membershipPairKeys);
        $orphanReleaseMemberships = array_values(array_filter(
            $this->releaseMembershipRows,
            fn ($m) => ! isset($cardKeySet[$m['card_key']]) || ! isset($releaseKeySet[$m['release_key']]),
        ));
        $membershipCountByCard = array_count_values(array_column($this->releaseMembershipRows, 'card_key'));
        $cardsWithMultipleReleaseMemberships = array_keys(array_filter($membershipCountByCard, fn ($n) => $n > 1));

        // Regression check for the exact bug class fixed this session: same
        // collector_number, same card name, AND overlapping rarity, across
        // more than one canonical set — a real duplicate canonical printing
        // (not just a coincidentally-reused code / intentional reprint with
        // a different rarity, which is fine and stays as-is).
        $cardsByCode = [];
        foreach ($this->cardRows as $c) {
            $cardsByCode[$c['collector_number']][] = $c;
        }
        $variantRaritiesByCardKey = [];
        foreach ($this->variantRows as $v) {
            $variantRaritiesByCardKey[$v['card_key']][] = $v['rarity'];
        }
        $canonicalDuplicateCandidates = [];
        foreach ($cardsByCode as $code => $cardsForCode) {
            $n = count($cardsForCode);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $cardsForCode[$i];
                    $b = $cardsForCode[$j];
                    if ($a['set_key'] === $b['set_key'] || $a['card_name'] !== $b['card_name']) {
                        continue;
                    }
                    $raritiesA = $variantRaritiesByCardKey[$a['card_key']] ?? [];
                    $raritiesB = $variantRaritiesByCardKey[$b['card_key']] ?? [];
                    if (array_intersect($raritiesA, $raritiesB) !== []) {
                        $canonicalDuplicateCandidates[] = ['card_number' => $code, 'card_a' => $a['card_key'], 'card_b' => $b['card_key']];
                    }
                }
            }
        }

        $failCounts = [
            'duplicate_release_memberships' => count($duplicateReleaseMemberships),
            'orphan_release_memberships' => count($orphanReleaseMemberships),
            'canonical_duplicate_candidates' => count($canonicalDuplicateCandidates),
            'duplicate_set_keys' => count($duplicateSetKeys),
            'duplicate_card_keys' => count($duplicateCardKeys),
            'duplicate_variant_keys' => count($duplicateVariantKeys),
            'duplicate_source_card_ids' => count($duplicateSourceCardIds),
            'orphan_cards' => count($orphanCards),
            'orphan_variants' => count($orphanVariants),
            'upstream_missing_card_data' => count($this->upstreamMissingCardData),
            'importer_missing_images' => $this->importerMissingImages,
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

            'primary_source_sets' => count($this->setRows),
            'supplemental_sets' => count($supplementalSetKeys),
            'generated_sets' => count($this->setActualCardCounts),
            'primary_sets' => count($setsWithPrimaryCard),
            'supplemental_only_sets' => count($supplementalOnlySetKeys),

            'primary_source_cards' => $primarySourceCards,
            'supplemental_cards' => $supplementalCards,
            'generated_cards' => count($this->cardRows),
            'generated_variants' => count($this->variantRows),
            'primary_cards' => $primarySourceCards,
            'supplemental_only_cards' => $supplementalCards,
            'supplemental_overridden_cards' => count($this->overriddenCardKeys),

            'skill_cards_included' => $this->skillCardsIncluded,
            'physical_tokens_included' => $this->physicalTokensIncluded,
            'tokens_excluded_nonphysical' => $this->tokensExcludedNonphysical,
            'unresolved_physical_tokens' => $this->unresolvedPhysicalTokens,
            'excluded_special_format' => $this->supplementalExcludedSpecialFormat,

            'duplicate_sets' => $duplicateSetKeys,
            'duplicate_cards' => $duplicateCardKeys,
            'duplicate_variants' => $duplicateVariantKeys,
            'duplicate_source_ids' => $duplicateSourceCardIds,

            'orphan_cards' => array_column($orphanCards, 'card_key'),
            'orphan_variants' => array_column($orphanVariants, 'variant_key'),

            'unresolved_source_gaps' => $this->upstreamMissingCardData,
            'unresolved_set_references' => $this->unresolvedSetReferences,
            'expected_unique_vs_numbered_differences' => $this->expectedUniqueVsNumberedDifferences,

            'fallback_card_artwork' => $this->fallbackArtworkCards,
            'ambiguous_multiple_artworks' => $this->ambiguousArtworkCards,
            'no_artwork' => $this->noArtworkCards,
            'importer_missing_images' => $this->importerMissingImages,

            'non_unique_set_codes_skipped' => $this->nonUniqueSetCodesSkipped,
            'non_unique_card_codes_skipped' => $this->nonUniqueCardCodesSkipped,

            'dropped_fully_excluded_sets' => $this->droppedFullyExcludedSets,

            'region_specific_variants' => $this->regionSpecificVariants,
            'edition_specific_variants' => $this->editionSpecificVariants,

            'release_memberships_created' => count($this->releaseMembershipRows),
            'release_memberships_unresolved' => $this->releaseMembershipsUnresolved,
            'cards_with_multiple_release_memberships' => count($cardsWithMultipleReleaseMemberships),
            'duplicate_release_memberships' => $duplicateReleaseMemberships,
            'orphan_release_memberships' => array_map(fn ($m) => "{$m['card_key']}:{$m['release_key']}", $orphanReleaseMemberships),
            'canonical_duplicate_candidates' => $canonicalDuplicateCandidates,
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
            ['primary_source_sets', $r['primary_source_sets']],
            ['supplemental_sets', $r['supplemental_sets']],
            ['generated_sets', $r['generated_sets']],
            ['primary_sets', $r['primary_sets']],
            ['supplemental_only_sets', $r['supplemental_only_sets']],
            ['primary_source_cards', $r['primary_source_cards']],
            ['supplemental_cards', $r['supplemental_cards']],
            ['generated_cards', $r['generated_cards']],
            ['generated_variants', $r['generated_variants']],
            ['primary_cards', $r['primary_cards']],
            ['supplemental_only_cards', $r['supplemental_only_cards']],
            ['supplemental_overridden_cards', $r['supplemental_overridden_cards']],
            ['skill_cards_included', $r['skill_cards_included']],
            ['physical_tokens_included', $r['physical_tokens_included']],
            ['tokens_excluded_nonphysical', $r['tokens_excluded_nonphysical']],
            ['unresolved_physical_tokens', count($r['unresolved_physical_tokens'])],
            ['excluded_special_format', count($r['excluded_special_format'])],
            ['duplicate_sets', count($r['duplicate_sets'])],
            ['duplicate_cards', count($r['duplicate_cards'])],
            ['duplicate_variants', count($r['duplicate_variants'])],
            ['duplicate_source_ids', count($r['duplicate_source_ids'])],
            ['orphan_cards', count($r['orphan_cards'])],
            ['orphan_variants', count($r['orphan_variants'])],
            ['unresolved_source_gaps (FAIL)', count($r['unresolved_source_gaps'])],
            ['unresolved_set_references (WARNING)', count($r['unresolved_set_references'])],
            ['expected_unique_vs_numbered_differences (WARNING)', count($r['expected_unique_vs_numbered_differences'])],
            ['fallback_card_artwork', $r['fallback_card_artwork']],
            ['ambiguous_multiple_artworks', $r['ambiguous_multiple_artworks']],
            ['no_artwork', $r['no_artwork']],
            ['importer_missing_images (FAIL)', $r['importer_missing_images']],
            ['non_unique_set_codes_skipped (info)', $r['non_unique_set_codes_skipped']],
            ['non_unique_card_codes_skipped (info)', $r['non_unique_card_codes_skipped']],
            ['dropped_fully_excluded_sets (info)', count($r['dropped_fully_excluded_sets'])],
            ['region_specific_variants', $r['region_specific_variants']],
            ['edition_specific_variants', $r['edition_specific_variants']],
            ['release_memberships_created', $r['release_memberships_created']],
            ['release_memberships_unresolved', $r['release_memberships_unresolved']],
            ['cards_with_multiple_release_memberships', $r['cards_with_multiple_release_memberships']],
            ['duplicate_release_memberships (FAIL)', count($r['duplicate_release_memberships'])],
            ['orphan_release_memberships (FAIL)', count($r['orphan_release_memberships'])],
            ['canonical_duplicate_candidates (FAIL)', count($r['canonical_duplicate_candidates'])],
            ['STATUS', $r['status']],
        ]);

        foreach (['duplicate_sets', 'duplicate_cards', 'duplicate_variants', 'duplicate_source_ids', 'orphan_cards', 'orphan_variants'] as $key) {
            if ($r[$key] !== []) {
                $this->warn(ucfirst(str_replace('_', ' ', $key)) . ': ' . implode(', ', array_slice($r[$key], 0, 20)));
            }
        }
        foreach ($r['excluded_special_format'] as $e) {
            $this->comment("  excluded (special format): {$e['set_name']} / {$e['card_id']} ({$e['card_name']}) — {$e['reason']}");
        }
        foreach ($r['dropped_fully_excluded_sets'] as $setName) {
            $this->comment("  dropped canonical set (zero real cards after exclusions): {$setName}");
        }
        foreach (array_slice($r['unresolved_source_gaps'], 0, 20) as $m) {
            $this->error("  unresolved_source_gap: {$m['set_key']} — expected {$m['expected']}, got {$m['actual']}");
        }
        foreach ($r['unresolved_set_references'] as $u) {
            $this->warn("  unresolved_set_reference: {$u['card_name']} -> \"{$u['set_name']}\" ({$u['set_code']})");
        }
        foreach ($r['canonical_duplicate_candidates'] as $c) {
            $this->error("  canonical_duplicate_candidate: {$c['card_number']} — {$c['card_a']} <-> {$c['card_b']}");
        }
    }
}
