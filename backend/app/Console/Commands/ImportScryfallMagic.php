<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fetches Magic: The Gathering catalog data from Scryfall's bulk data
 * export (default_cards.jsonl.gz — one card object per line, English/
 * default-language printings) and writes it out as the same 5-CSV format
 * cardora:binder-import-v2 consumes — it does NOT write to the database
 * itself.
 *
 * Card identity, deliberately NOT (set, collector_number)-based:
 * card_key = "magic:scryfall:{scryfall_id}" — the Scryfall `id` is the
 * authoritative printing identity. set/collector_number/language are
 * searchable/display metadata columns, never the key, so an odd collector
 * number or a variation can never accidentally merge two real printings.
 *
 * Four distinct concepts, mapped deliberately:
 *   Oracle card / card design  -> binder_cards.oracle_id (nullable column,
 *                                 not a table)
 *   Physical printing          -> binder_cards (one row per Scryfall card
 *                                 object)
 *   Finish (nonfoil/foil/etched) -> binder_card_variants (one row per
 *                                 entry actually present in `finishes` --
 *                                 never fabricated)
 *   Release/product membership -> NOT generated here. promo_types is
 *                                 preserved verbatim as JSON on
 *                                 binder_cards for later curated review --
 *                                 it mixes distribution context
 *                                 (prerelease/buyabox), classification
 *                                 (boosterfun/universesbeyond), and
 *                                 treatment (surgefoil/confettifoil),
 *                                 which need human judgement to separate,
 *                                 never an automatic rule.
 *
 * Physical-only catalog: digital=true cards are excluded and counted.
 * Nothing else is auto-excluded by layout or set_type (tokens, emblems,
 * schemes, planes, vanguards, art series, oversized, memorabilia all stay
 * IN as ordinary cards) -- every non-digital card object must become
 * exactly one binder_cards row. Scryfall's `oversized` flag is preserved
 * via physical_format_code (nullable, "oversized" | null) rather than
 * excluding anything, so Pokémon Jumbo cards can reuse the same column
 * later without another schema change.
 *
 * Streaming end-to-end, bounded memory regardless of catalog size: the
 * bulk file is read line-by-line (gzgets, never loaded whole), and every
 * generated CSV is written incrementally through an open file handle as
 * each row is produced -- no cards/variants/external_ids array is ever
 * held in full. Uniqueness/orphan checks use small hash sets of just the
 * ID strings involved (tens of MB for ~110k cards), never the full rows.
 *
 * Dry-run by default: always generates the CSVs + a global validation
 * report. Pass --apply to additionally hand the generated directory to
 * cardora:binder-import-v2, and only if the report is PASS or
 * PASS_WITH_WARNINGS.
 */
class ImportScryfallMagic extends Command
{
    protected $signature = 'cardora:scryfall-import-magic
        {--out= : Directory to write games/sets/cards/variants/external_ids CSVs into (default: storage/app/scryfall-magic)}
        {--apply : Also run cardora:binder-import-v2 on the generated CSVs. Only runs on PASS or PASS_WITH_WARNINGS. Without this flag nothing touches the database.}
        {--refresh-cache : Ignore the persistent on-disk cache and re-fetch/re-download from Scryfall (results still get cached)}';

    protected $description = 'Fetch Magic: The Gathering catalog data from Scryfall bulk data and generate catalog v2 import CSVs, with a dry-run validation report';

    private const BULK_DATA_LIST_URL = 'https://api.scryfall.com/bulk-data';
    private const SETS_LIST_URL = 'https://api.scryfall.com/sets';
    private const CACHE_DIR = 'scryfall-cache';
    /** Scryfall rejects requests carrying an HTTP library's default User-Agent (HTTP 400, rule=generic_user_agent) and asks for an explicit Accept header too. */
    private const USER_AGENT = 'Cardora/1.0 (+https://cardora.gr; catalog importer)';
    private const ACCEPT_HEADER = 'application/json;q=0.9,*/*;q=0.8';

    /** @var array<string,resource> */
    private array $handles = [];

    private int $sourceObjects = 0;
    private int $physicalSourceObjects = 0;
    private int $excludedDigitalObjects = 0;

    private array $sourceSetCodes = []; // set_code => true, seen across ALL objects (physical + digital)
    private array $setsWithPhysicalCards = []; // set_code => true
    private array $digitalOnlySetCounts = []; // set_code => count of digital cards seen (for sets with zero physical cards)
    private array $writtenSetKeys = []; // set_key => true, dedupe guard for the incrementally-written sets.csv

    private int $generatedSets = 0;
    private int $generatedCards = 0;
    private int $generatedVariants = 0;

    private array $scryfallIdsSeen = []; // id => true (hash set, not a growing list) + a small overflow list of actual dupes
    private array $duplicateScryfallIds = [];
    private array $variantKeysSeen = [];
    private array $duplicateVariantKeys = [];
    private array $cardKeysSeen = [];
    private array $duplicateCardKeys = [];

    private int $orphanCards = 0;
    private int $orphanVariants = 0;
    private int $brokenSetReferences = 0;

    private array $oracleIdsSeen = [];
    private int $cardsWithOracleId = 0;
    private int $cardsWithoutOracleId = 0;

    private array $finishCounts = ['nonfoil' => 0, 'foil' => 0, 'etched' => 0];
    private array $unexpectedFinishCounts = [];
    private int $cardsWithMultipleFinishes = 0;
    private int $cardsWithNoFinishes = 0;

    private array $languageDistribution = [];

    private int $multifacedCards = 0;
    private int $multifacedUsingFaceImageFallback = 0;
    private int $multifacedMissingAllImages = 0;

    private int $upstreamMissingImages = 0;
    private int $importerMissingImages = 0;
    private int $missingImageEnrichmentRows = 0;

    private int $oversizedCards = 0;
    private int $cardsWithPromoTypes = 0;
    private int $promoTypesPreserved = 0;
    private array $uniquePromoTypesSeen = [];

    private array $specialPhysicalTypeCandidates = []; // category => ['count'=>int, 'examples'=>[]]
    private array $promoTypeFrequency = []; // promo_type => ['count'=>int, 'example_cards'=>[], 'example_sets'=>[]]

    private array $bulkMeta = [];
    private array $setsMeta = [];

    public function handle(): int
    {
        $outDir = rtrim((string) ($this->option('out') ?: storage_path('app/scryfall-magic')), '/');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");

            return self::FAILURE;
        }

        $gitCommit = $this->currentGitCommit();
        $this->info("Importer version: {$gitCommit['hash']}" . ($gitCommit['dirty'] ? ' (dirty working tree)' : ''));

        $this->info('Fetching sets metadata (cached, single call + pagination, not per-set)...');
        $setsByCode = $this->fetchSetsMetadata();
        if ($setsByCode === null) {
            $this->error('Could not reach Scryfall /sets — aborting.');

            return self::FAILURE;
        }
        $this->info('Sets known to Scryfall: ' . count($setsByCode));

        $this->info('Resolving default_cards bulk data file...');
        $bulkFilePath = $this->downloadBulkFile();
        if ($bulkFilePath === null) {
            $this->error('Could not download default_cards bulk data — aborting.');

            return self::FAILURE;
        }

        $this->openCsvHandles($outDir);

        $this->info('Streaming and processing card objects (bounded memory, incremental writes)...');
        $this->processBulkFile($bulkFilePath, $setsByCode);
        $this->info("Source objects: {$this->sourceObjects} (physical: {$this->physicalSourceObjects}, digital excluded: {$this->excludedDigitalObjects})");

        $this->closeCsvHandles();

        $peakMemoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $this->info("Peak memory: {$peakMemoryMb} MB");

        $report = $this->buildReport($gitCommit, $peakMemoryMb);
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

    /**
     * GET /sets, following pagination, cached as a single frozen file
     * (storage/app/scryfall-cache/sets.json) rather than a per-set call
     * for every one of the ~1100 sets. Small (~1100 rows), kept fully in
     * memory deliberately -- this is not the part of the pipeline that
     * scales with catalog size.
     *
     * @return array<string,array<string,mixed>>|null set_code => set object
     */
    private function fetchSetsMetadata(): ?array
    {
        $cachePath = storage_path('app/' . self::CACHE_DIR . '/sets.json');
        if (! $this->option('refresh-cache') && is_file($cachePath)) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if ($cached !== null) {
                $this->setsMeta = [
                    'source' => 'cache',
                    'sha256' => hash('sha256', (string) file_get_contents($cachePath)),
                    'cached_path' => $cachePath,
                ];

                return $this->indexSetsByCode($cached);
            }
        }

        $allSets = [];
        $url = self::SETS_LIST_URL;
        while ($url !== null) {
            $page = $this->getJson($url);
            if ($page === null) {
                return null;
            }
            foreach ($page['data'] ?? [] as $set) {
                $allSets[] = $set;
            }
            $url = ($page['has_more'] ?? false) ? ($page['next_page'] ?? null) : null;
        }

        $dir = dirname($cachePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($allSets);
        file_put_contents($cachePath, $json);
        $this->setsMeta = [
            'source' => 'live',
            'sha256' => hash('sha256', $json),
            'cached_path' => $cachePath,
            'set_count' => count($allSets),
        ];

        return $this->indexSetsByCode($allSets);
    }

    /** @param array<int,array<string,mixed>> $sets @return array<string,array<string,mixed>> */
    private function indexSetsByCode(array $sets): array
    {
        $byCode = [];
        foreach ($sets as $s) {
            $byCode[$s['code']] = $s;
        }

        return $byCode;
    }

    /**
     * Resolves the default_cards bulk_data entry and downloads its
     * jsonl.gz, cached by TYPE (not by URL — the download URL embeds a
     * daily timestamp, so caching by URL would never hit). Records
     * provenance (updated_at, download URI, sha256) for the report/manifest.
     */
    private function downloadBulkFile(): ?string
    {
        $cachePath = storage_path('app/' . self::CACHE_DIR . '/default_cards.jsonl.gz');
        $metaPath = storage_path('app/' . self::CACHE_DIR . '/default_cards.meta.json');

        if (! $this->option('refresh-cache') && is_file($cachePath) && is_file($metaPath)) {
            $this->bulkMeta = json_decode((string) file_get_contents($metaPath), true) ?? [];
            $this->bulkMeta['source'] = 'cache';

            return $cachePath;
        }

        $list = $this->getJson(self::BULK_DATA_LIST_URL);
        if ($list === null) {
            return null;
        }
        $entry = null;
        foreach ($list['data'] ?? [] as $item) {
            if (($item['type'] ?? null) === 'default_cards') {
                $entry = $item;

                break;
            }
        }
        if ($entry === null) {
            $this->error('default_cards entry not found in bulk-data list.');

            return null;
        }

        $this->info("Downloading {$entry['name']} ({$entry['compressed_size']} bytes, updated {$entry['updated_at']})...");
        $dir = dirname($cachePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $maxAttempts = 3;
        $body = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(600)->withHeaders(['User-Agent' => self::USER_AGENT])->get($entry['jsonl_download_uri']);
                if ($response->successful()) {
                    $body = $response->body();

                    break;
                }
            } catch (\Throwable $e) {
                $this->warn("  download attempt {$attempt} failed: {$e->getMessage()}");
            }
            if ($attempt < $maxAttempts) {
                usleep(1_000_000 * (2 ** ($attempt - 1)));
            }
        }
        if ($body === null) {
            $this->error('Failed to download default_cards bulk file after retries.');

            return null;
        }

        file_put_contents($cachePath, $body);
        $sha256 = hash('sha256', $body);
        unset($body); // don't hold the 75-300MB body in memory a moment longer than necessary

        $this->bulkMeta = [
            'source' => 'live',
            'bulk_data_id' => $entry['id'],
            'type' => $entry['type'],
            'updated_at' => $entry['updated_at'],
            'jsonl_download_uri' => $entry['jsonl_download_uri'],
            'compressed_size' => $entry['compressed_size'],
            'sha256' => $sha256,
            'downloaded_at' => now()->toIso8601String(),
        ];
        file_put_contents($metaPath, json_encode($this->bulkMeta, JSON_PRETTY_PRINT));

        return $cachePath;
    }

    private function openCsvHandles(string $outDir): void
    {
        $this->handles['sets'] = fopen("{$outDir}/sets.csv", 'w');
        fputcsv($this->handles['sets'], [
            'game_slug', 'set_key', 'set_name', 'abbreviation', 'set_code', 'set_type', 'language',
            'region', 'released_at', 'base_total', 'numbered_total', 'source', 'source_set_id', 'source_url',
        ]);

        $this->handles['cards'] = fopen("{$outDir}/cards.csv", 'w');
        fputcsv($this->handles['cards'], [
            'game_slug', 'set_key', 'card_key', 'oracle_id', 'physical_format_code', 'promo_types',
            'card_name', 'clean_name', 'collector_number', 'card_type', 'is_promo', 'is_token', 'language',
        ]);

        $this->handles['variants'] = fopen("{$outDir}/variants.csv", 'w');
        fputcsv($this->handles['variants'], [
            'card_key', 'variant_key', 'variant_name', 'variant_type', 'rarity', 'region_code', 'edition_code', 'artist',
            'image_small', 'image_large', 'sort_order',
        ]);

        $this->handles['external_ids'] = fopen("{$outDir}/external_ids.csv", 'w');
        fputcsv($this->handles['external_ids'], [
            'entity_type', 'entity_key', 'provider', 'external_id', 'external_type', 'external_url',
        ]);

        $this->handles['missing_images'] = fopen("{$outDir}/missing_images_enrichment.csv", 'w');
        fputcsv($this->handles['missing_images'], [
            'card_key', 'scryfall_id', 'card_name', 'set_code', 'collector_number', 'reason',
        ]);

        $this->handles['games'] = fopen("{$outDir}/games.csv", 'w');
        fputcsv($this->handles['games'], ['slug', 'name', 'category', 'sort_order']);
        fputcsv($this->handles['games'], ['magic-the-gathering', 'Magic: The Gathering', 'tcg', 30]);
        fclose($this->handles['games']);
        unset($this->handles['games']);
    }

    private function closeCsvHandles(): void
    {
        foreach ($this->handles as $handle) {
            fclose($handle);
        }
        $this->handles = [];
    }

    /** @param array<string,array<string,mixed>> $setsByCode */
    private function processBulkFile(string $path, array $setsByCode): void
    {
        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            $this->error("Could not open {$path} for streaming.");

            return;
        }

        while (($line = gzgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || $line === '[' || $line === ']') {
                continue; // top-level array brackets/blank lines, defensive
            }
            $line = rtrim($line, ',');
            $card = json_decode($line, true);
            unset($line); // don't hold the raw line string alongside the decoded array
            if (! is_array($card) || ! isset($card['id'])) {
                continue;
            }

            $this->sourceObjects++;
            $setCode = $card['set'] ?? '';
            $this->sourceSetCodes[$setCode] = true;

            if ($card['digital'] ?? false) {
                $this->excludedDigitalObjects++;
                $this->digitalOnlySetCounts[$setCode] = ($this->digitalOnlySetCounts[$setCode] ?? 0) + 1;

                continue;
            }

            $this->physicalSourceObjects++;
            $this->setsWithPhysicalCards[$setCode] = true;
            $this->processCard($card, $setsByCode);
        }
        gzclose($handle);
    }

    /** @param array<string,mixed> $card @param array<string,array<string,mixed>> $setsByCode */
    private function processCard(array $card, array $setsByCode): void
    {
        $scryfallId = $card['id'];
        if (isset($this->scryfallIdsSeen[$scryfallId])) {
            $this->duplicateScryfallIds[] = $scryfallId;
        } else {
            $this->scryfallIdsSeen[$scryfallId] = true;
        }
        $cardKey = "magic:scryfall:{$scryfallId}";
        if (isset($this->cardKeysSeen[$cardKey])) {
            $this->duplicateCardKeys[] = $cardKey;
        } else {
            $this->cardKeysSeen[$cardKey] = true;
        }

        $setCode = $card['set'] ?? '';
        $setKey = 'magic-' . $this->slug($setCode);
        $this->registerSet($setKey, $setCode, $card, $setsByCode);
        // Structural guarantee, verified rather than assumed: the set this
        // card claims must exist (we just wrote it, or it already existed).
        if (! isset($this->writtenSetKeys[$setKey])) {
            $this->brokenSetReferences++;
        }

        $isMultifaced = isset($card['card_faces']) && is_array($card['card_faces']) && count($card['card_faces']) > 0;
        $faces = $isMultifaced ? $card['card_faces'] : [];
        if ($isMultifaced) {
            $this->multifacedCards++;
        }

        // Oracle ID: absent at the top level only for reversible_card layout,
        // where it lives on each face instead.
        $oracleId = $card['oracle_id'] ?? ($faces[0]['oracle_id'] ?? null);
        if ($oracleId !== null) {
            $this->cardsWithOracleId++;
            $this->oracleIdsSeen[$oracleId] = true;
        } else {
            $this->cardsWithoutOracleId++;
        }

        // Image resolution: own image_uris first; multifaced cards with no
        // parent-level image_uris (transform/modal_dfc/double_faced_token)
        // fall back to the first face's image_uris.
        $imageUris = $card['image_uris'] ?? null;
        $usedFaceFallback = false;
        if ($imageUris === null && $isMultifaced && isset($faces[0]['image_uris'])) {
            $imageUris = $faces[0]['image_uris'];
            $usedFaceFallback = true;
        }

        $imageSmall = $imageUris['small'] ?? '';
        $imageLarge = $imageUris['large'] ?? $imageUris['normal'] ?? $imageUris['png'] ?? '';

        if ($usedFaceFallback) {
            $this->multifacedUsingFaceImageFallback++;
        }
        $reason = null;
        if ($imageUris === null || ($imageSmall === '' && $imageLarge === '')) {
            $this->upstreamMissingImages++;
            $reason = 'upstream_missing_image';
            if ($isMultifaced) {
                $this->multifacedMissingAllImages++;
                $reason = 'multifaced_missing_all_images';
            }
        } elseif ($imageSmall === '' && $imageLarge === '') {
            // imageUris was non-empty upstream but our extraction produced
            // nothing usable -- an importer bug, not an upstream data gap.
            $this->importerMissingImages++;
        }
        if ($reason !== null) {
            fputcsv($this->handles['missing_images'], [
                $cardKey, $scryfallId, $card['name'] ?? '', $setCode, $card['collector_number'] ?? '', $reason,
            ]);
            $this->missingImageEnrichmentRows++;
        }

        $lang = strtoupper((string) ($card['lang'] ?? 'en'));
        $this->languageDistribution[$lang] = ($this->languageDistribution[$lang] ?? 0) + 1;

        $isToken = in_array($card['layout'] ?? '', ['token', 'double_faced_token'], true)
            || str_starts_with((string) ($card['type_line'] ?? ''), 'Token');

        $physicalFormatCode = ($card['oversized'] ?? false) === true ? 'oversized' : '';
        if ($physicalFormatCode === 'oversized') {
            $this->oversizedCards++;
        }

        $promoTypesJson = '';
        $promoTypes = $card['promo_types'] ?? [];
        if ($promoTypes !== []) {
            $promoTypesJson = json_encode(array_values($promoTypes));
            $this->cardsWithPromoTypes++;
            foreach ($promoTypes as $pt) {
                $this->uniquePromoTypesSeen[$pt] = true;
            }
        }

        fputcsv($this->handles['cards'], [
            'magic-the-gathering', $setKey, $cardKey, $oracleId ?? '', $physicalFormatCode, $promoTypesJson,
            $card['name'] ?? $cardKey, $card['name'] ?? $cardKey, $card['collector_number'] ?? '',
            $card['type_line'] ?? '', ($card['promo'] ?? false) ? 'TRUE' : 'FALSE', $isToken ? 'TRUE' : 'FALSE', $lang,
        ]);
        if ($promoTypesJson !== '') {
            $this->promoTypesPreserved++;
        }
        $this->generatedCards++;

        $this->buildVariants($card, $cardKey, $imageSmall, $imageLarge);
        $this->buildExternalIds($card, $cardKey);
        $this->classifySpecialPhysicalType($card, $setCode);
        $this->tallyPromoTypes($promoTypes, $card, $setCode);
    }

    /** @param array<string,mixed> $card */
    private function buildVariants(array $card, string $cardKey, string $imageSmall, string $imageLarge): void
    {
        $finishes = $card['finishes'] ?? [];
        if ($finishes === []) {
            $this->cardsWithNoFinishes++;

            return; // never fabricate a finish that isn't actually listed
        }
        if (count($finishes) > 1) {
            $this->cardsWithMultipleFinishes++;
        }

        $sortOrder = 1;
        foreach ($finishes as $finish) {
            if (isset($this->finishCounts[$finish])) {
                $this->finishCounts[$finish]++;
            } else {
                $this->unexpectedFinishCounts[$finish] = ($this->unexpectedFinishCounts[$finish] ?? 0) + 1;
            }

            $variantKey = "{$cardKey}:{$finish}";
            if (isset($this->variantKeysSeen[$variantKey])) {
                $this->duplicateVariantKeys[] = $variantKey;
            } else {
                $this->variantKeysSeen[$variantKey] = true;
            }
            // Structural guarantee, verified: the card this variant belongs
            // to must already have been written in this same call chain.
            if (! isset($this->cardKeysSeen[$cardKey])) {
                $this->orphanVariants++;
            }

            fputcsv($this->handles['variants'], [
                $cardKey, $variantKey, ucfirst(str_replace('_', ' ', $finish)), $finish, $card['rarity'] ?? '',
                '', '', $card['artist'] ?? '', $imageSmall, $imageLarge, $sortOrder++,
            ]);
            $this->generatedVariants++;
        }
    }

    /** @param array<string,mixed> $card */
    private function buildExternalIds(array $card, string $cardKey): void
    {
        $add = function (string $provider, string $type, mixed $value, string $entityType = 'card', ?string $entityKey = null) use ($cardKey): void {
            if ($value === null || $value === '') {
                return;
            }
            fputcsv($this->handles['external_ids'], [$entityType, $entityKey ?? $cardKey, $provider, (string) $value, $type, '']);
        };

        $add('scryfall', 'scryfall_id', $card['id'] ?? null);
        $add('cardmarket', 'cardmarket_id', $card['cardmarket_id'] ?? null);
        $add('tcgplayer', 'tcgplayer_id', $card['tcgplayer_id'] ?? null);
        if (isset($card['tcgplayer_etched_id']) && in_array('etched', $card['finishes'] ?? [], true)) {
            $add('tcgplayer', 'tcgplayer_etched_id', $card['tcgplayer_etched_id'], 'variant', "{$cardKey}:etched");
        }
        // Digital-platform identifiers: provenance only, never used to
        // create cards/variants.
        $add('mtgo', 'mtgo_id', $card['mtgo_id'] ?? null);
        $add('mtgo', 'mtgo_foil_id', $card['mtgo_foil_id'] ?? null);
        $add('arena', 'arena_id', $card['arena_id'] ?? null);
        foreach ($card['multiverse_ids'] ?? [] as $multiverseId) {
            $add('gatherer', 'multiverse_id', $multiverseId);
        }
    }

    /** @param array<string,mixed> $card */
    private function classifySpecialPhysicalType(array $card, string $setCode): void
    {
        $layout = $card['layout'] ?? '';
        $setType = $card['set_type'] ?? '';
        $category = match (true) {
            in_array($layout, ['token', 'double_faced_token'], true) => 'token',
            $layout === 'emblem' => 'emblem',
            $layout === 'scheme' => 'scheme',
            $layout === 'planar' => 'planar',
            $layout === 'vanguard' => 'vanguard',
            $layout === 'art_series' => 'art_series',
            ($card['oversized'] ?? false) === true => 'oversized',
            $setType === 'memorabilia' => 'memorabilia',
            $setType === 'minigame' => 'minigame',
            default => null,
        };
        if ($category === null) {
            return;
        }

        $this->specialPhysicalTypeCandidates[$category]['count'] = ($this->specialPhysicalTypeCandidates[$category]['count'] ?? 0) + 1;
        if (count($this->specialPhysicalTypeCandidates[$category]['examples'] ?? []) < 5) {
            $this->specialPhysicalTypeCandidates[$category]['examples'][] = [
                'name' => $card['name'] ?? '',
                'set' => $setCode,
                'collector_number' => $card['collector_number'] ?? '',
            ];
        }
    }

    /** @param array<int,string> $promoTypes @param array<string,mixed> $card */
    private function tallyPromoTypes(array $promoTypes, array $card, string $setCode): void
    {
        foreach ($promoTypes as $promoType) {
            $this->promoTypeFrequency[$promoType]['count'] = ($this->promoTypeFrequency[$promoType]['count'] ?? 0) + 1;
            if (count($this->promoTypeFrequency[$promoType]['example_cards'] ?? []) < 3) {
                $this->promoTypeFrequency[$promoType]['example_cards'][] = $card['name'] ?? '';
            }
            if (! in_array($setCode, $this->promoTypeFrequency[$promoType]['example_sets'] ?? [], true)
                && count($this->promoTypeFrequency[$promoType]['example_sets'] ?? []) < 3) {
                $this->promoTypeFrequency[$promoType]['example_sets'][] = $setCode;
            }
        }
    }

    /** @param array<string,mixed> $card @param array<string,array<string,mixed>> $setsByCode */
    private function registerSet(string $setKey, string $setCode, array $card, array $setsByCode): void
    {
        if (isset($this->writtenSetKeys[$setKey])) {
            return;
        }

        $meta = $setsByCode[$setCode] ?? null;
        fputcsv($this->handles['sets'], [
            'magic-the-gathering', $setKey, $meta['name'] ?? $card['set_name'] ?? $setCode, $setCode, $setCode,
            $meta['set_type'] ?? $card['set_type'] ?? '', 'EN', '', $meta['released_at'] ?? $card['released_at'] ?? '',
            $meta['printed_size'] ?? $meta['card_count'] ?? '', $meta['card_count'] ?? '', 'scryfall',
            $meta['id'] ?? $card['set_id'] ?? '', $meta['scryfall_uri'] ?? $card['scryfall_set_uri'] ?? '',
        ]);
        $this->writtenSetKeys[$setKey] = true;
        $this->generatedSets++;
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
        $dirty = trim((string) shell_exec("git -C {$repoDir} status --porcelain -- app/Console/Commands/ImportScryfallMagic.php 2>&1")) !== '';

        return [
            'hash' => str_starts_with($hash, 'fatal') ? 'unknown' : $hash,
            'short' => str_starts_with($short, 'fatal') ? 'unknown' : $short,
            'dirty' => $dirty,
        ];
    }

    private function getJson(string $url): ?array
    {
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(60)->withHeaders([
                    'Accept' => self::ACCEPT_HEADER,
                    'User-Agent' => self::USER_AGENT,
                ])->get($url);
                if ($response->successful()) {
                    return $response->json();
                }
                $this->warn("  fetch failed: {$url} (HTTP {$response->status()})");
            } catch (\Throwable $e) {
                $this->warn("  fetch failed: {$url} ({$e->getMessage()})");
            }
            if ($attempt < $maxAttempts) {
                usleep(500_000 * (2 ** ($attempt - 1)));
            }
        }

        return null;
    }

    /** @param array{hash:string,short:string,dirty:bool} $gitCommit */
    private function buildReport(array $gitCommit, float $peakMemoryMb): array
    {
        $excludedDigitalBySet = [];
        foreach ($this->digitalOnlySetCounts as $code => $count) {
            if (! isset($this->setsWithPhysicalCards[$code])) {
                $excludedDigitalBySet[] = ['set_code' => $code, 'digital_card_count' => $count];
            }
        }

        $failCounts = [
            'duplicate_scryfall_ids' => count($this->duplicateScryfallIds),
            'duplicate_card_keys' => count($this->duplicateCardKeys),
            'duplicate_variant_keys' => count($this->duplicateVariantKeys),
            'orphan_cards' => $this->orphanCards,
            'orphan_variants' => $this->orphanVariants,
            'broken_set_references' => $this->brokenSetReferences,
            'importer_missing_images' => $this->importerMissingImages,
            'physical_objects_silently_dropped' => max(0, $this->physicalSourceObjects - $this->generatedCards),
        ];
        $warningCounts = [
            'upstream_missing_images' => $this->upstreamMissingImages,
            'cards_without_oracle_id' => $this->cardsWithoutOracleId,
            'unexpected_finish_values' => array_sum($this->unexpectedFinishCounts),
            'multifaced_missing_all_images' => $this->multifacedMissingAllImages,
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
            'peak_memory_mb' => $peakMemoryMb,
            'bulk_data' => $this->bulkMeta,
            'sets_metadata' => $this->setsMeta,

            'source_objects' => $this->sourceObjects,
            'physical_source_objects' => $this->physicalSourceObjects,
            'excluded_digital_objects' => $this->excludedDigitalObjects,

            'source_sets' => count($this->sourceSetCodes),
            'generated_sets' => $this->generatedSets,
            'generated_cards' => $this->generatedCards,
            'generated_variants' => $this->generatedVariants,

            'unique_scryfall_ids' => count($this->scryfallIdsSeen),
            'duplicate_scryfall_ids' => $this->duplicateScryfallIds,
            'duplicate_card_keys' => $this->duplicateCardKeys,
            'duplicate_variant_keys' => $this->duplicateVariantKeys,

            'orphan_cards' => $this->orphanCards,
            'orphan_variants' => $this->orphanVariants,
            'broken_set_references' => $this->brokenSetReferences,

            'cards_with_oracle_id' => $this->cardsWithOracleId,
            'cards_without_oracle_id' => $this->cardsWithoutOracleId,
            'unique_oracle_ids' => count($this->oracleIdsSeen),

            'finish_nonfoil' => $this->finishCounts['nonfoil'],
            'finish_foil' => $this->finishCounts['foil'],
            'finish_etched' => $this->finishCounts['etched'],
            'unexpected_finish_values' => $this->unexpectedFinishCounts,
            'cards_with_multiple_finishes' => $this->cardsWithMultipleFinishes,
            'cards_with_no_finishes' => $this->cardsWithNoFinishes,

            'language_distribution' => $this->languageDistribution,

            'multifaced_cards' => $this->multifacedCards,
            'multifaced_using_face_image_fallback' => $this->multifacedUsingFaceImageFallback,
            'multifaced_missing_all_images' => $this->multifacedMissingAllImages,

            'upstream_missing_images' => $this->upstreamMissingImages,
            'importer_missing_images' => $this->importerMissingImages,
            'missing_images_enrichment_rows' => $this->missingImageEnrichmentRows,

            'oversized_cards' => $this->oversizedCards,
            'cards_with_promo_types' => $this->cardsWithPromoTypes,
            'unique_promo_types' => count($this->uniquePromoTypesSeen),
            'promo_types_preserved' => $this->promoTypesPreserved,

            'excluded_digital_by_set' => $excludedDigitalBySet,

            'special_physical_type_candidates' => $this->specialPhysicalTypeCandidates,
            'promo_type_frequency' => $this->promoTypeFrequency,

            'physical_objects_silently_dropped' => $failCounts['physical_objects_silently_dropped'],
        ];
    }

    private function printReport(array $r): void
    {
        $this->newLine();
        $this->info("=== Validation report (importer {$r['importer_git_commit_short']}) ===");
        $this->table(['Metric', 'Value'], [
            ['peak_memory_mb', $r['peak_memory_mb']],
            ['source_objects', $r['source_objects']],
            ['physical_source_objects', $r['physical_source_objects']],
            ['excluded_digital_objects', $r['excluded_digital_objects']],
            ['source_sets', $r['source_sets']],
            ['generated_sets', $r['generated_sets']],
            ['generated_cards', $r['generated_cards']],
            ['generated_variants', $r['generated_variants']],
            ['unique_scryfall_ids', $r['unique_scryfall_ids']],
            ['duplicate_scryfall_ids (FAIL)', count($r['duplicate_scryfall_ids'])],
            ['duplicate_card_keys (FAIL)', count($r['duplicate_card_keys'])],
            ['duplicate_variant_keys (FAIL)', count($r['duplicate_variant_keys'])],
            ['orphan_cards (FAIL)', $r['orphan_cards']],
            ['orphan_variants (FAIL)', $r['orphan_variants']],
            ['broken_set_references (FAIL)', $r['broken_set_references']],
            ['physical_objects_silently_dropped (FAIL)', $r['physical_objects_silently_dropped']],
            ['importer_missing_images (FAIL)', $r['importer_missing_images']],
            ['cards_with_oracle_id', $r['cards_with_oracle_id']],
            ['cards_without_oracle_id (WARNING)', $r['cards_without_oracle_id']],
            ['unique_oracle_ids', $r['unique_oracle_ids']],
            ['finish_nonfoil', $r['finish_nonfoil']],
            ['finish_foil', $r['finish_foil']],
            ['finish_etched', $r['finish_etched']],
            ['unexpected_finish_values (WARNING)', array_sum($r['unexpected_finish_values'])],
            ['cards_with_multiple_finishes', $r['cards_with_multiple_finishes']],
            ['cards_with_no_finishes', $r['cards_with_no_finishes']],
            ['multifaced_cards', $r['multifaced_cards']],
            ['multifaced_using_face_image_fallback', $r['multifaced_using_face_image_fallback']],
            ['multifaced_missing_all_images (WARNING)', $r['multifaced_missing_all_images']],
            ['upstream_missing_images (WARNING)', $r['upstream_missing_images']],
            ['missing_images_enrichment_rows', $r['missing_images_enrichment_rows']],
            ['oversized_cards', $r['oversized_cards']],
            ['cards_with_promo_types', $r['cards_with_promo_types']],
            ['unique_promo_types', $r['unique_promo_types']],
            ['promo_types_preserved', $r['promo_types_preserved']],
            ['STATUS', $r['status']],
        ]);

        $this->newLine();
        $this->comment('Language distribution:');
        $langs = $r['language_distribution'];
        arsort($langs);
        foreach ($langs as $lang => $count) {
            $this->line("  {$lang}: {$count}");
        }

        $this->newLine();
        $this->comment('Special physical type candidates (all KEPT — for awareness, not exclusion):');
        foreach ($r['special_physical_type_candidates'] as $category => $data) {
            $this->line("  {$category}: {$data['count']}");
        }

        $this->newLine();
        $this->comment('Promo type frequency (top 30, preserved as JSON on binder_cards, NOT converted to releases):');
        $promoTypes = $r['promo_type_frequency'];
        uasort($promoTypes, fn ($a, $b) => $b['count'] <=> $a['count']);
        foreach (array_slice($promoTypes, 0, 30, true) as $type => $data) {
            $this->line("  {$type}: {$data['count']}");
        }

        if ($r['excluded_digital_by_set'] !== []) {
            $this->newLine();
            $this->warn('Sets excluded entirely (100% digital): ' . count($r['excluded_digital_by_set']));
        }
    }
}
