<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;

/**
 * NBA / Soccer / EuroLeague canonical v2 catalog, from manually-collected
 * checklist spreadsheets in the private `vrealmxr/cardora-sets-data` repo
 * (folder `CARDSETS/`, synced to this server at
 * `../cardora-sets-data/CARDSETS/`, same sibling-of-repo convention as the
 * legacy TCGplayer `cardora-sets-data` folder). No public structured API
 * exists for sports card checklists (see docs/binder-data-provenance-audit.md
 * research) -- this is the accepted fallback, explicitly without images or
 * full gameplay stats, per the product decision to use what's available.
 *
 * Canonical identity, verified against real data before writing this
 * (2024-25 Panini Prizm Basketball: 343 distinct "Set" parallel names, each
 * with exactly the same 270 (Number, Name) pairs): within one product,
 * (Number, Name) is the canonical card. Every parallel/insert variant of
 * that card repeats the identical (Number, Name) under a different "Set"
 * value -- so grouping by (Number, Name) inside one product file is a safe,
 * mechanical way to split canonical card vs. variant, no color/parallel
 * keyword guessing required.
 */
class ImportCardoraChecklists extends Command
{
    protected $signature = 'cardora:import-checklists
        {--sport= : nba|soccer|euroleague}
        {--apply : Write to the database. Without this flag, only a dry-run report is produced.}
        {--data-dir= : Path to the CARDSETS folder (default: ../cardora-sets-data/CARDSETS next to the repo)}
        {--only= : Comma-separated product name substrings to limit the run to (for piloting)}';

    protected $description = 'Import NBA/Soccer/EuroLeague canonical v2 catalog from cardora-sets-data checklist spreadsheets';

    private const SPORT_FOLDERS = [
        'nba' => 'NBA',
        'soccer' => 'Soccer',
        'euroleague' => 'EuroLeague',
    ];

    /** Header synonyms -> canonical column name. Matched case-insensitively, trimmed. */
    private const HEADER_SYNONYMS = [
        'set' => 'set', 'card set' => 'set',
        'number' => 'number', 'card #' => 'number', 'card number' => 'number', '#' => 'number',
        'name' => 'name', 'athlete' => 'name', 'player' => 'name',
        'team' => 'team',
        'print run' => 'print_run', 'print runs' => 'print_run', 'seq' => 'print_run', 'sequence' => 'print_run',
    ];

    private array $report = [
        'products_scanned' => 0,
        'products_parsed' => 0,
        'products_skipped_no_file' => 0,
        'products_skipped_pdf_only' => 0,
        'rows_read' => 0,
        'cards_written' => 0,
        'variants_written' => 0,
        'duplicate_card_keys' => 0,
        'duplicate_variant_keys' => 0,
        'missing_number_or_name' => 0,
        'unresolved' => [],
    ];

    public function handle(): int
    {
        // PhpSpreadsheet is memory-hungry even in read-only mode; the default
        // CLI 512M limit isn't enough headroom across 200+ products.
        ini_set('memory_limit', '2G');

        $sportSlug = $this->option('sport');
        if (! $sportSlug || ! isset(self::SPORT_FOLDERS[$sportSlug])) {
            $this->error('Pass --sport=nba|soccer|euroleague');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $onlyFilter = $this->option('only') ? array_map('trim', explode(',', $this->option('only'))) : null;

        $dataDir = $this->option('data-dir') ?: base_path('../cardora-sets-data/CARDSETS');
        $manifestPath = $dataDir.'/checklist_manifest.json';
        if (! is_file($manifestPath)) {
            $this->error("Manifest not found: {$manifestPath}");

            return self::FAILURE;
        }

        $game = BinderGame::query()->where('slug', $sportSlug)->first();
        if (! $game) {
            $this->error("binder_games row for {$sportSlug} not found.");

            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $sportFolder = self::SPORT_FOLDERS[$sportSlug];
        $products = array_filter($manifest, fn ($e) => $e['sport'] === $sportFolder);

        if ($onlyFilter) {
            $products = array_filter($products, function ($e) use ($onlyFilter) {
                foreach ($onlyFilter as $needle) {
                    if (stripos($e['name'], $needle) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        $this->info('Products to scan: '.count($products));

        $seenCardKeys = [];
        $seenVariantKeys = [];
        $allCanonical = []; // card_key => ['card' => [...], 'variants' => [variant_key => [...]]]
        $setsToWrite = [];

        foreach ($products as $entry) {
            $this->report['products_scanned']++;
            if ($this->report['products_scanned'] % 20 === 0) {
                $mb = round(memory_get_usage(true) / 1_000_000);
                $this->info("... {$this->report['products_scanned']}/".count($products)." scanned, {$mb}MB used");
            }

            $file = $this->pickBestFile($entry, $dataDir);
            if (! $file) {
                if (empty($entry['files'])) {
                    $this->report['products_skipped_no_file']++;
                } else {
                    $this->report['products_skipped_pdf_only']++;
                    $this->report['unresolved'][] = "PDF-only, not parsed this pass: {$entry['name']}";
                }
                continue;
            }

            $productSlug = Str::slug($entry['season'].'-'.$entry['name']);
            $setKey = "{$sportSlug}-en-{$productSlug}";
            $setsToWrite[$setKey] = [
                'name' => $entry['title'] ?? $entry['name'],
                'season' => $entry['season'],
                'source_url' => $entry['source'] ?? null,
            ];

            $rows = $this->readRows($file);
            if ($rows === null) {
                $this->report['unresolved'][] = "Could not parse file for: {$entry['name']} ({$file})";
                continue;
            }
            $this->report['products_parsed']++;

            foreach ($rows as $row) {
                $this->report['rows_read']++;
                $number = $row['number'] ?? null;
                $name = $row['name'] ?? null;
                if ($number === null || $number === '' || ! $name) {
                    $this->report['missing_number_or_name']++;
                    continue;
                }

                $cardKey = $this->capKey($setKey.'-'.Str::slug((string) $number).'-'.Str::slug($name));
                $variantLabel = $row['set'] ?: 'Base';
                if (! empty($row['print_run'])) {
                    $variantLabel .= ' /'.$row['print_run'];
                }
                $variantKey = $this->capKey($cardKey.':'.Str::slug($variantLabel));

                if (! isset($allCanonical[$cardKey])) {
                    $allCanonical[$cardKey] = [
                        'set_key' => $setKey,
                        'number' => (string) $number,
                        'name' => $this->capText($name),
                        'team' => $row['team'] ?? null,
                        'card_type' => $this->capText($row['set'] ?: 'Base'),
                        'variants' => [],
                    ];
                } elseif ($allCanonical[$cardKey]['set_key'] !== $setKey) {
                    // Structurally impossible given set_key is baked into
                    // cardKey, kept as a tripwire.
                    $this->report['duplicate_card_keys']++;
                }

                if (isset($allCanonical[$cardKey]['variants'][$variantKey])) {
                    $this->report['duplicate_variant_keys']++;
                    $this->report['unresolved'][] = "duplicate variant_key {$variantKey}";
                    continue;
                }

                $allCanonical[$cardKey]['variants'][$variantKey] = [
                    'variant_name' => $this->capText($variantLabel),
                    'print_run' => $row['print_run'] ?? null,
                ];
            }
        }

        $totalVariants = array_sum(array_map(fn ($c) => count($c['variants']), $allCanonical));

        $this->info('--- Checklist import report ('.$sportSlug.') ---');
        $this->report['cards_written'] = count($allCanonical);
        $this->report['variants_written'] = $totalVariants;
        $this->printReport();

        if (! $apply) {
            return self::SUCCESS;
        }

        $this->writeToDatabase($game, $setsToWrite, $allCanonical);

        $this->info('Write complete.');

        return self::SUCCESS;
    }

    /**
     * Prefer xlsx/xls; PDF-only products are logged as unresolved and
     * skipped this pass (see class docblock / plan).
     */
    private function pickBestFile(array $entry, string $dataDir): ?string
    {
        foreach ($entry['files'] ?? [] as $f) {
            if (in_array($f['format'], ['xlsx', 'xls'], true)) {
                $relative = $this->relativizePath($f['path'], $entry, $dataDir);
                if ($relative && is_file($relative)) {
                    return $relative;
                }
            }
        }

        return null;
    }

    /**
     * Manifest `path` values are absolute Windows paths from the machine
     * that built this manifest -- rebuild the path relative to our own
     * dataDir using sport/season/name/filename instead of trusting the
     * stored absolute path. `entry['sport']` is already the folder name
     * ("NBA"/"Soccer"/"EuroLeague"), matching CARDSETS/<sport>/<season>/<name>/.
     */
    private function relativizePath(string $manifestPath, array $entry, string $dataDir): ?string
    {
        $filename = basename(str_replace('\\', '/', $manifestPath));

        return "{$dataDir}/{$entry['sport']}/{$entry['season']}/{$entry['name']}/{$filename}";
    }

    /**
     * @return array<int,array{set:?string,number:mixed,name:?string,team:?string,print_run:mixed}>|null
     */
    private function readRows(string $path): ?array
    {
        try {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $reader = $ext === 'xls' ? new XlsReader() : new XlsxReader();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheet(0);
        } catch (\Throwable $e) {
            return null;
        }

        try {
            $highestRow = $sheet->getHighestDataRow();
            $highestCol = $sheet->getHighestDataColumn();

            // Pass 1: find a header row among the first 5 rows using known
            // synonyms. If none found, fall back to the Topps-style positional
            // parser (section-title rows + Number/Name/Team data rows, no
            // header at all -- confirmed structure, see class docblock).
            $columnMap = null;
            $headerRowIndex = null;
            for ($r = 1; $r <= min(5, $highestRow); $r++) {
                $cells = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, false)[0];
                $map = $this->matchHeader($cells);
                if ($map && isset($map['number']) && isset($map['name'])) {
                    $columnMap = $map;
                    $headerRowIndex = $r;
                    break;
                }
            }

            return $columnMap !== null
                ? $this->readTabular($sheet, $headerRowIndex + 1, $highestRow, $highestCol, $columnMap)
                : $this->readPositionalWithSections($sheet, $highestRow, $highestCol);
        } finally {
            // PhpSpreadsheet worksheets hold circular references the
            // refcounting GC can't reclaim on its own; across 200+ products
            // (some 20k-40k rows) that leaks enough to OOM-kill the process
            // with no PHP exception ever printed. Must disconnect + force a
            // cycle collection after every single file.
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $sheet);
            gc_collect_cycles();
        }
    }

    /** @return array<string,int>|null column name => 0-based index */
    private function matchHeader(array $cells): ?array
    {
        $map = [];
        foreach (array_values($cells) as $i => $cell) {
            $key = strtolower(trim((string) $cell));
            if (isset(self::HEADER_SYNONYMS[$key])) {
                $map[self::HEADER_SYNONYMS[$key]] = $i;
            }
        }

        return $map ?: null;
    }

    private function readTabular($sheet, int $startRow, int $highestRow, string $highestCol, array $columnMap): array
    {
        $rows = [];
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $cells = array_values($sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, false)[0]);
            if (! array_filter($cells, fn ($c) => $c !== null && $c !== '')) {
                continue;
            }
            $rows[] = [
                'set' => isset($columnMap['set']) ? trim((string) ($cells[$columnMap['set']] ?? '')) : null,
                'number' => $cells[$columnMap['number']] ?? null,
                'name' => isset($cells[$columnMap['name']]) ? trim((string) $cells[$columnMap['name']]) : null,
                'team' => isset($columnMap['team']) ? trim((string) ($cells[$columnMap['team']] ?? '')) : null,
                'print_run' => isset($columnMap['print_run']) ? ($cells[$columnMap['print_run']] ?? null) : null,
            ];
        }

        return $rows;
    }

    /**
     * Topps-style layout confirmed on real data (e.g. "2025-26 Topps Hopps
     * Basketball"): no header row; a row with exactly one non-empty cell is
     * a subset/section title (e.g. "BASE CARDS I"); a row with a numeric
     * first cell and a second cell is a data row (Number, Name, Team, ...)
     * belonging to the most recently seen section title.
     */
    private function readPositionalWithSections($sheet, int $highestRow, string $highestCol): array
    {
        $rows = [];
        $currentSection = 'Base';

        for ($r = 1; $r <= $highestRow; $r++) {
            $cells = array_values($sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, false)[0]);
            $nonEmpty = array_values(array_filter($cells, fn ($c) => $c !== null && trim((string) $c) !== ''));

            if (count($nonEmpty) === 0) {
                continue;
            }

            if (count($nonEmpty) === 1 && ! is_numeric($nonEmpty[0])) {
                $currentSection = trim((string) $nonEmpty[0]);
                continue;
            }

            if (is_numeric($cells[0] ?? null) && ! empty(trim((string) ($cells[1] ?? '')))) {
                $rows[] = [
                    'set' => $currentSection,
                    'number' => $cells[0],
                    'name' => trim((string) $cells[1]),
                    'team' => trim((string) ($cells[2] ?? '')),
                    'print_run' => null,
                ];
            }
        }

        return $rows;
    }

    private function writeToDatabase(BinderGame $game, array $setsToWrite, array $allCanonical): void
    {
        $setIdByKey = [];
        foreach ($setsToWrite as $setKey => $meta) {
            DB::table('binder_sets')->updateOrInsert(
                ['set_key' => $setKey],
                [
                    'game_id' => $game->id,
                    'slug' => $setKey,
                    'source' => 'cardora-sets-data',
                    'source_url' => $meta['source_url'],
                    'name' => $meta['name'],
                    'abbreviation' => $meta['season'],
                    'set_code' => $meta['season'],
                    'set_type' => 'main',
                    'language' => 'EN',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
        $setIdByKey = DB::table('binder_sets')->where('game_id', $game->id)->pluck('id', 'set_key')->all();

        $cardBuffer = [];
        $cardKeyToTemp = [];
        foreach ($allCanonical as $cardKey => $card) {
            $cardBuffer[] = [
                'card_key' => $cardKey,
                'set_id' => $setIdByKey[$card['set_key']],
                'game_id' => $game->id,
                'name' => $card['name'],
                'clean_name' => $card['name'],
                'number' => $card['number'],
                'card_type' => $card['card_type'],
                'is_promo' => false,
                'is_token' => false,
                'language' => 'EN',
                'gameplay_data' => json_encode([
                    'schema_version' => 1,
                    'source' => 'cardora-sets-data',
                    'resolved_at' => now()->toIso8601String(),
                    'provenance' => [],
                    'data' => ['team' => $card['team']],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($cardBuffer) >= 500) {
                DB::table('binder_cards')->upsert($cardBuffer, ['card_key'], ['set_id', 'game_id', 'name', 'clean_name', 'number', 'card_type', 'gameplay_data', 'updated_at']);
                $cardBuffer = [];
            }
        }
        if ($cardBuffer) {
            DB::table('binder_cards')->upsert($cardBuffer, ['card_key'], ['set_id', 'game_id', 'name', 'clean_name', 'number', 'card_type', 'gameplay_data', 'updated_at']);
        }

        $cardIdByKey = DB::table('binder_cards')->where('game_id', $game->id)->pluck('id', 'card_key')->all();

        $variantBuffer = [];
        $sortByCard = [];
        foreach ($allCanonical as $cardKey => $card) {
            $cardId = $cardIdByKey[$cardKey] ?? null;
            if (! $cardId) {
                continue;
            }
            foreach ($card['variants'] as $variantKey => $variant) {
                $sortByCard[$cardKey] = ($sortByCard[$cardKey] ?? 0) + 1;
                $variantBuffer[] = [
                    'variant_key' => $variantKey,
                    'card_id' => $cardId,
                    'source_variant_kind' => Str::slug($variant['variant_name']) === 'base' ? 'base' : 'alt',
                    'variant_name' => $variant['variant_name'],
                    'variant_type' => Str::slug($variant['variant_name']),
                    'sort_order' => $sortByCard[$cardKey],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($variantBuffer) >= 500) {
                    DB::table('binder_card_variants')->upsert($variantBuffer, ['variant_key'], ['card_id', 'variant_name', 'variant_type', 'source_variant_kind', 'sort_order', 'updated_at']);
                    $variantBuffer = [];
                }
            }
        }
        if ($variantBuffer) {
            DB::table('binder_card_variants')->upsert($variantBuffer, ['variant_key'], ['card_id', 'variant_name', 'variant_type', 'source_variant_kind', 'sort_order', 'updated_at']);
        }
    }

    /**
     * A handful of real checklist rows are "header/checklist" cards whose
     * Player field lists every subject at once (e.g. "Rookie Sweaters
     * Header Checklist" -> 30 concatenated names), blowing well past the
     * varchar(255) card_key/variant_key columns. Cap deterministically and
     * append a content hash so uniqueness survives the truncation, and log
     * it so the anomaly stays visible rather than silently mangled.
     */
    private function capKey(string $key, int $max = 255): string
    {
        if (mb_strlen($key) <= $max) {
            return $key;
        }

        $hash = substr(md5($key), 0, 8);
        $this->report['unresolved'][] = 'key exceeded '.$max." chars, truncated with hash suffix: ".mb_substr($key, 0, 80).'... (full length '.mb_strlen($key).')';

        return mb_substr($key, 0, $max - 9).'-'.$hash;
    }

    private function capText(?string $text, int $max = 255): ?string
    {
        if ($text === null || mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }

    private function printReport(): void
    {
        foreach ($this->report as $key => $value) {
            if ($key === 'unresolved') {
                continue;
            }
            $this->line("{$key}: ".$value);
        }
        if (! empty($this->report['unresolved'])) {
            $this->warn('Unresolved ('.count($this->report['unresolved']).'), showing first 30:');
            foreach (array_slice($this->report['unresolved'], 0, 30) as $line) {
                $this->line('  - '.$line);
            }
        }
    }
}
