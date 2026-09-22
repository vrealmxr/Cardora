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
 *                                 not a table -- see the migration)
 *   Physical printing          -> binder_cards (one row per Scryfall card
 *                                 object)
 *   Finish (nonfoil/foil/etched) -> binder_card_variants (one row per
 *                                 entry actually present in `finishes` --
 *                                 never fabricated)
 *   Release/product membership -> NOT generated here. promo_types and
 *                                 product-like set_types are only reported
 *                                 (promo_type_frequency /
 *                                 special_physical_type_candidates) for a
 *                                 separate, human-reviewed pass -- same
 *                                 principle as the Yu-Gi-Oh! KACB/PCY work.
 *
 * Physical-only catalog: digital=true cards are excluded and counted
 * (excluded_digital_objects). Nothing else is auto-excluded by layout or
 * set_type (tokens, emblems, schemes, planes, vanguards, art series,
 * oversized, memorabilia, minigame sets all stay IN as ordinary cards) --
 * every non-digital card object must become exactly one binder_cards row,
 * enforced as a hard FAIL if any are silently dropped.
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
    /** Scryfall rejects requests carrying an HTTP library's default User-Agent (HTTP 400, rule=generic_user_agent). */
    private const USER_AGENT = 'Cardora/1.0 (+https://cardora.gr; catalog importer)';

    private array $setRows = [];
    private array $cardRows = [];
    private array $variantRows = [];
    private array $externalIdRows = [];

    private int $sourceObjects = 0;
    private int $physicalSourceObjects = 0;
    private int $excludedDigitalObjects = 0;

    private array $sourceSetCodes = []; // set_code => true, seen across ALL objects (physical + digital)
    private array $setsWithPhysicalCards = []; // set_code => true
    private array $digitalOnlySetCounts = []; // set_code => count of digital cards seen (for sets with zero physical cards)

    private array $scryfallIdsSeen = [];
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

        $this->info('Streaming and processing card objects...');
        $this->processBulkFile($bulkFilePath, $setsByCode);
        $this->info("Source objects: {$this->sourceObjects} (physical: {$this->physicalSourceObjects}, digital excluded: {$this->excludedDigitalObjects})");

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

    /**
     * GET /sets, following pagination, cached as a single frozen file
     * (storage/app/scryfall-cache/sets.json) rather than a per-set call
     * for every one of the ~1100 sets.
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
        $this->scryfallIdsSeen[] = $scryfallId;
        $cardKey = "magic:scryfall:{$scryfallId}";

        $setCode = $card['set'] ?? '';
        $setKey = 'magic-' . $this->slug($setCode);
        $this->registerSet($setCode, $setKey, $card, $setsByCode);

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
        if ($imageUris === null || ($imageSmall === '' && $imageLarge === '')) {
            $this->upstreamMissingImages++;
            if ($isMultifaced) {
                $this->multifacedMissingAllImages++;
            }
        } elseif ($imageSmall === '' && $imageLarge === '') {
            // imageUris was non-empty upstream but our extraction produced
            // nothing usable -- an importer bug, not an upstream data gap.
            $this->importerMissingImages++;
        }

        $lang = strtoupper((string) ($card['lang'] ?? 'en'));
        $this->languageDistribution[$lang] = ($this->languageDistribution[$lang] ?? 0) + 1;

        $isToken = in_array($card['layout'] ?? '', ['token', 'double_faced_token'], true)
            || str_starts_with((string) ($card['type_line'] ?? ''), 'Token');

        $this->cardRows[] = [
            'game_slug' => 'magic-the-gathering',
            'set_key' => $setKey,
            'card_key' => $cardKey,
            'oracle_id' => $oracleId ?? '',
            'card_name' => $card['name'] ?? $cardKey,
            'clean_name' => $card['name'] ?? $cardKey,
            'collector_number' => $card['collector_number'] ?? '',
            'card_type' => $card['type_line'] ?? '',
            'is_promo' => ($card['promo'] ?? false) ? 'TRUE' : 'FALSE',
            'is_token' => $isToken ? 'TRUE' : 'FALSE',
            'language' => $lang,
        ];

        $this->buildVariants($card, $cardKey, $imageSmall, $imageLarge);
        $this->buildExternalIds($card, $cardKey);
        $this->classifySpecialPhysicalType($card, $setCode);
        $this->tallyPromoTypes($card, $setCode);
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

            $this->variantRows[] = [
                'card_key' => $cardKey,
                'variant_key' => "{$cardKey}:{$finish}",
                'variant_name' => ucfirst(str_replace('_', ' ', $finish)),
                'variant_type' => $finish,
                'rarity' => $card['rarity'] ?? '',
                'region_code' => '',
                'edition_code' => '',
                'artist' => $card['artist'] ?? '',
                'image_small' => $imageSmall,
                'image_large' => $imageLarge,
                'sort_order' => $sortOrder++,
            ];
        }
    }

    /** @param array<string,mixed> $card */
    private function buildExternalIds(array $card, string $cardKey): void
    {
        $add = function (string $provider, string $type, mixed $value, string $entityType = 'card', ?string $entityKey = null) use ($cardKey): void {
            if ($value === null || $value === '') {
                return;
            }
            $this->externalIdRows[] = [
                'entity_type' => $entityType,
                'entity_key' => $entityKey ?? $cardKey,
                'provider' => $provider,
                'external_id' => (string) $value,
                'external_type' => $type,
                'external_url' => '',
            ];
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
        foreach ($card['multiverse_ids'] ?? [] as $i => $multiverseId) {
            $this->externalIdRows[] = [
                'entity_type' => 'card',
                'entity_key' => $cardKey,
                'provider' => 'gatherer',
                'external_id' => (string) $multiverseId,
                'external_type' => 'multiverse_id',
                'external_url' => '',
            ];
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

    /** @param array<string,mixed> $card */
    private function tallyPromoTypes(array $card, string $setCode): void
    {
        foreach ($card['promo_types'] ?? [] as $promoType) {
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
    private function registerSet(string $setCode, string $setKey, array $card, array $setsByCode): void
    {
        if (isset($this->setRows[$setKey])) {
            return;
        }

        $meta = $setsByCode[$setCode] ?? null;
        $this->setRows[$setKey] = [
            'game_slug' => 'magic-the-gathering',
            'set_key' => $setKey,
            'set_name' => $meta['name'] ?? $card['set_name'] ?? $setCode,
            'abbreviation' => $setCode,
            'set_code' => $setCode,
            'set_type' => $meta['set_type'] ?? $card['set_type'] ?? '',
            'language' => 'EN',
            'region' => '',
            'released_at' => $meta['released_at'] ?? $card['released_at'] ?? '',
            'base_total' => $meta['printed_size'] ?? $meta['card_count'] ?? '',
            'numbered_total' => $meta['card_count'] ?? '',
            'source' => 'scryfall',
            'source_set_id' => $meta['id'] ?? $card['set_id'] ?? '',
            'source_url' => $meta['scryfall_uri'] ?? $card['scryfall_set_uri'] ?? '',
        ];
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
                $response = Http::timeout(60)->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])->get($url);
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

    private function writeCsvs(string $outDir): void
    {
        $this->writeCsv("{$outDir}/games.csv", ['slug', 'name', 'category', 'sort_order'], [
            ['magic-the-gathering', 'Magic: The Gathering', 'tcg', 30],
        ]);
        $this->writeCsv("{$outDir}/sets.csv", [
            'game_slug', 'set_key', 'set_name', 'abbreviation', 'set_code', 'set_type', 'language',
            'region', 'released_at', 'base_total', 'numbered_total', 'source', 'source_set_id', 'source_url',
        ], array_values($this->setRows));
        $this->writeCsv("{$outDir}/cards.csv", [
            'game_slug', 'set_key', 'card_key', 'oracle_id', 'card_name', 'clean_name', 'collector_number',
            'card_type', 'is_promo', 'is_token', 'language',
        ], $this->cardRows);
        $this->writeCsv("{$outDir}/variants.csv", [
            'card_key', 'variant_key', 'variant_name', 'variant_type', 'rarity', 'region_code', 'edition_code', 'artist',
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
        $cardKeys = array_column($this->cardRows, 'card_key');
        $variantKeys = array_column($this->variantRows, 'variant_key');
        $setKeys = array_column($this->setRows, 'set_key');

        $duplicateScryfallIds = $this->duplicates($this->scryfallIdsSeen);
        $duplicateCardKeys = $this->duplicates($cardKeys);
        $duplicateVariantKeys = $this->duplicates($variantKeys);

        $setKeySet = array_flip($setKeys);
        $orphanCards = array_values(array_filter($this->cardRows, fn ($c) => ! isset($setKeySet[$c['set_key']])));
        $cardKeySet = array_flip($cardKeys);
        $orphanVariants = array_values(array_filter($this->variantRows, fn ($v) => ! isset($cardKeySet[$v['card_key']])));
        $brokenSetReferences = $orphanCards; // same structural check, named per spec

        $excludedDigitalBySet = [];
        foreach ($this->digitalOnlySetCounts as $code => $count) {
            if (! isset($this->setsWithPhysicalCards[$code])) {
                $excludedDigitalBySet[] = ['set_code' => $code, 'digital_card_count' => $count];
            }
        }

        $failCounts = [
            'duplicate_scryfall_ids' => count($duplicateScryfallIds),
            'duplicate_card_keys' => count($duplicateCardKeys),
            'duplicate_variant_keys' => count($duplicateVariantKeys),
            'orphan_cards' => count($orphanCards),
            'orphan_variants' => count($orphanVariants),
            'broken_set_references' => count($brokenSetReferences),
            'importer_missing_images' => $this->importerMissingImages,
            'physical_objects_silently_dropped' => max(0, $this->physicalSourceObjects - count($this->cardRows)),
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
            'bulk_data' => $this->bulkMeta,
            'sets_metadata' => $this->setsMeta,

            'source_objects' => $this->sourceObjects,
            'physical_source_objects' => $this->physicalSourceObjects,
            'excluded_digital_objects' => $this->excludedDigitalObjects,

            'source_sets' => count($this->sourceSetCodes),
            'generated_sets' => count($this->setRows),
            'generated_cards' => count($this->cardRows),
            'generated_variants' => count($this->variantRows),

            'unique_scryfall_ids' => count(array_unique($this->scryfallIdsSeen)),
            'duplicate_scryfall_ids' => $duplicateScryfallIds,
            'duplicate_card_keys' => $duplicateCardKeys,
            'duplicate_variant_keys' => $duplicateVariantKeys,

            'orphan_cards' => array_column($orphanCards, 'card_key'),
            'orphan_variants' => array_column($orphanVariants, 'variant_key'),
            'broken_set_references' => array_column($brokenSetReferences, 'card_key'),

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
            ['orphan_cards (FAIL)', count($r['orphan_cards'])],
            ['orphan_variants (FAIL)', count($r['orphan_variants'])],
            ['broken_set_references (FAIL)', count($r['broken_set_references'])],
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
            ['STATUS', $r['status']],
        ]);

        $this->newLine();
        $this->comment('Language distribution:');
        arsort($r['language_distribution']);
        foreach ($r['language_distribution'] as $lang => $count) {
            $this->line("  {$lang}: {$count}");
        }

        $this->newLine();
        $this->comment('Special physical type candidates (NOT excluded — for review):');
        foreach ($r['special_physical_type_candidates'] as $category => $data) {
            $this->line("  {$category}: {$data['count']}");
        }

        $this->newLine();
        $this->comment('Promo type frequency (top 30, NOT converted to releases yet):');
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
