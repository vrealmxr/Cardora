<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Full-catalog export for feeding an external image-recognition training
 * pipeline: one row per v2-canonical variant (the same rows Binder itself
 * shows -- legacy pre-v2 catalog rows are excluded, same filter as
 * BinderController), with every identifying field plus a working,
 * absolute image URL. Streamed to CSV via a DB cursor so ~1.6M rows never
 * sit in memory at once.
 */
class ExportBinderCatalogManifest extends Command
{
    protected $signature = 'cardora:export-catalog-manifest {--out=storage/app/binder-catalog-manifest.csv} {--category= : Filter to a single binder_games.category (e.g. tcg, sports)}';

    protected $description = 'Export the full v2 Binder catalog (all 11 games) as a single CSV manifest for external tooling (e.g. training an image-recognition bot)';

    private const IMAGE_ORIGIN_BASE_URL = 'https://api.cardora.gr';

    public function handle(): int
    {
        // PDO's MySQL driver buffers the entire result set client-side by
        // default -- Laravel's ->cursor() does NOT disable that on its own,
        // so a ~1.6M-row 4-table join was getting fully materialized
        // before the first generator iteration and OOM-killed the process
        // silently (confirmed: 0 rows written, empty log, same signature
        // as the sports-importer memory leak earlier this session).
        // chunkById keeps each batch bounded regardless of total size.
        $outPath = base_path($this->option('out'));
        $handle = fopen($outPath, 'w');
        if (! $handle) {
            $this->error("Could not open {$outPath} for writing.");

            return self::FAILURE;
        }

        fputcsv($handle, [
            'game_slug', 'game_name', 'game_category',
            'set_key', 'set_name', 'set_code', 'set_released_at',
            'card_key', 'card_name', 'card_number', 'rarity', 'card_type', 'team',
            'variant_key', 'variant_name', 'variant_type', 'artist',
            'image_url', 'has_image',
        ]);

        $count = 0;
        $lastId = 0;
        $pageSize = 5000;
        $categoryFilter = $this->option('category');

        // Laravel's fluent builder always emits joins in FROM-clause order
        // but can't inject the STRAIGHT_JOIN hint -- and it turns out to
        // matter a lot here: without it, MySQL's optimizer drives this
        // join from binder_games (tiny, ~10 rows) outward instead of using
        // binder_card_variants' primary key for the WHERE id > ?  ORDER BY
        // id LIMIT page, so it was materializing and filesorting nearly
        // the *entire* ~1.6M-row join on every single page (confirmed:
        // 6.5s for one 2000-row page; identical query with STRAIGHT_JOIN
        // added, 0.017s -- a ~380x difference). Raw SQL + manual
        // keyset pagination it is.
        while (true) {
            $bindings = [$lastId];
            $categorySql = '';
            if ($categoryFilter) {
                $categorySql = 'AND g.category = ?';
                $bindings[] = $categoryFilter;
            }
            $bindings[] = $pageSize;

            $rows = DB::select("
                SELECT STRAIGHT_JOIN
                    v.id,
                    g.slug as game_slug, g.name as game_name, g.category as game_category,
                    s.set_key, s.name as set_name, s.set_code, s.released_at as set_released_at,
                    c.card_key, c.name as card_name, c.number as card_number,
                    c.rarity, c.card_type, c.gameplay_data,
                    v.variant_key, v.variant_name, v.variant_type, v.artist,
                    c.image_url as card_image_url, v.image_small, v.image_large
                FROM binder_card_variants v
                JOIN binder_cards c ON c.id = v.card_id
                JOIN binder_sets s ON s.id = c.set_id
                JOIN binder_games g ON g.id = c.game_id
                WHERE g.binder_enabled = 1 AND c.card_key IS NOT NULL AND v.id > ? {$categorySql}
                ORDER BY v.id
                LIMIT ?
            ", $bindings);

            if (! $rows) {
                break;
            }

            foreach ($rows as $row) {
                $imageUrl = self::absolutizeImageUrl($row->image_large ?: $row->image_small ?: $row->card_image_url);
                $team = null;
                if ($row->gameplay_data) {
                    $decoded = json_decode($row->gameplay_data, true);
                    $team = $decoded['data']['team'] ?? null;
                }

                fputcsv($handle, [
                    $row->game_slug, $row->game_name, $row->game_category,
                    $row->set_key, $row->set_name, $row->set_code, $row->set_released_at,
                    $row->card_key, $row->card_name, $row->card_number, $row->rarity, $row->card_type, $team,
                    $row->variant_key, $row->variant_name, $row->variant_type, $row->artist,
                    $imageUrl, $imageUrl ? '1' : '0',
                ]);

                $count++;
                $lastId = $row->id;
            }

            $this->info("... {$count} rows written");

            if (count($rows) < $pageSize) {
                break;
            }
        }

        fclose($handle);

        $this->info("Done. {$count} rows written to {$outPath}");

        return self::SUCCESS;
    }

    private static function absolutizeImageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return self::IMAGE_ORIGIN_BASE_URL.'/'.ltrim($url, '/');
    }
}
