<?php

namespace App\Console\Commands;

use App\Models\BinderCard;
use App\Models\BinderCardExternalId;
use App\Models\BinderCardVariant;
use App\Models\BinderGame;
use App\Models\BinderSet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportBinderCatalogV2 extends Command
{
    protected $signature = 'cardora:binder-import-v2
        {--path= : Directory containing games.csv, sets.csv, cards.csv, variants.csv, external_ids.csv, and optionally releases.csv/release_memberships.csv}
        {--only= : Comma-separated list of files to import (e.g. games,sets or release_memberships)}';

    protected $description = 'Import the Games/Sets/Cards/Variants/External_IDs catalog template (canonical card + variant model), plus the optional additive Releases/Card_Release_Memberships layer, into binder_* tables';

    private const CHUNK = 500;

    public function handle(): int
    {
        $dir = rtrim((string) $this->option('path'), '/');

        if ($dir === '' || ! is_dir($dir)) {
            $this->error('Pass --path=/path/to/csv/dir (must contain games.csv, sets.csv, cards.csv, variants.csv, external_ids.csv)');

            return self::FAILURE;
        }

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $run = fn (string $name) => $only === [] || in_array($name, $only, true);

        $gameIdBySlug = [];
        $setIdByKey = [];
        $cardIdByKey = [];
        $variantIdByKey = [];

        if ($run('games') && is_file("{$dir}/games.csv")) {
            $gameIdBySlug = $this->importGames("{$dir}/games.csv");
        } else {
            $gameIdBySlug = DB::table('binder_games')->pluck('id', 'slug')->map(fn ($v) => (int) $v)->all();
        }

        if ($run('sets') && is_file("{$dir}/sets.csv")) {
            $setIdByKey = $this->importSets("{$dir}/sets.csv", $gameIdBySlug);
        } else {
            $setIdByKey = DB::table('binder_sets')->whereNotNull('set_key')->pluck('id', 'set_key')->map(fn ($v) => (int) $v)->all();
        }

        if ($run('cards') && is_file("{$dir}/cards.csv")) {
            $cardIdByKey = $this->importCards("{$dir}/cards.csv", $gameIdBySlug, $setIdByKey);
        } else {
            $cardIdByKey = DB::table('binder_cards')->whereNotNull('card_key')->pluck('id', 'card_key')->map(fn ($v) => (int) $v)->all();
        }

        if ($run('variants') && is_file("{$dir}/variants.csv")) {
            $variantIdByKey = $this->importVariants("{$dir}/variants.csv", $cardIdByKey);
        } else {
            $variantIdByKey = DB::table('binder_card_variants')->pluck('id', 'variant_key')->map(fn ($v) => (int) $v)->all();
        }

        if ($run('external_ids') && is_file("{$dir}/external_ids.csv")) {
            $this->importExternalIds("{$dir}/external_ids.csv", $setIdByKey, $cardIdByKey, $variantIdByKey);
        }

        $releaseIdByKey = [];
        if ($run('releases') && is_file("{$dir}/releases.csv")) {
            $releaseIdByKey = $this->importReleases("{$dir}/releases.csv", $gameIdBySlug);
        } else {
            $releaseIdByKey = DB::table('binder_releases')->pluck('id', 'release_key')->map(fn ($v) => (int) $v)->all();
        }

        if ($run('release_memberships') && is_file("{$dir}/release_memberships.csv")) {
            $this->importReleaseMemberships("{$dir}/release_memberships.csv", $cardIdByKey, $releaseIdByKey);
        }

        $this->refreshSetCardCounts(array_values($setIdByKey));

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    /** @return array<string,int> slug => id */
    private function importGames(string $path): array
    {
        [$header, $handle] = $this->openCsv($path);
        $now = now();
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $r = $this->record($header, $row);
            if (! $r || ($r['slug'] ?? '') === '') {
                continue;
            }

            $rows[] = [
                'slug' => $r['slug'],
                'name' => $r['name'] ?? $r['slug'],
                'category' => $r['category'] ?? 'tcg',
                'sort_order' => (int) ($r['sort_order'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('binder_games')->upsert($chunk, ['slug'], ['name', 'category', 'sort_order', 'updated_at']);
        }

        $this->line('games.csv: ' . count($rows) . ' games');

        return DB::table('binder_games')->pluck('id', 'slug')->map(fn ($v) => (int) $v)->all();
    }

    /** @param array<string,int> $gameIdBySlug @return array<string,int> set_key => id */
    private function importSets(string $path, array $gameIdBySlug): array
    {
        [$header, $handle] = $this->openCsv($path);
        $now = now();
        $rows = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $r = $this->record($header, $row);
            if (! $r || ($r['set_key'] ?? '') === '') {
                continue;
            }

            $gameId = $gameIdBySlug[$r['game_slug'] ?? ''] ?? null;
            if ($gameId === null) {
                $skipped++;

                continue;
            }

            $rows[$r['set_key']] = [
                'game_id' => $gameId,
                'external_group_id' => null,
                'slug' => $r['set_key'],
                'set_key' => $r['set_key'],
                'source' => $this->nullIfEmpty($r['source'] ?? null),
                'source_set_id' => $this->nullIfEmpty($r['source_set_id'] ?? null),
                'source_url' => $this->nullIfEmpty($r['source_url'] ?? null),
                'name' => $r['set_name'] ?? $r['set_key'],
                'abbreviation' => $this->nullIfEmpty($r['abbreviation'] ?? null),
                'set_code' => $this->nullIfEmpty($r['set_code'] ?? null),
                'set_type' => $this->nullIfEmpty($r['set_type'] ?? null),
                'language' => $this->nullIfEmpty($r['language'] ?? null) ?? 'EN',
                'region' => $this->nullIfEmpty($r['region'] ?? null),
                'released_at' => $this->parseDate($r['released_at'] ?? null),
                'base_total' => $this->nullIfEmpty($r['base_total'] ?? null),
                'numbered_total' => $this->nullIfEmpty($r['numbered_total'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if ($skipped > 0) {
            $this->warn("  sets.csv: skipped {$skipped} rows with unknown game_slug");
        }

        foreach (array_chunk($rows, self::CHUNK, true) as $chunk) {
            DB::table('binder_sets')->upsert(
                array_values($chunk),
                ['set_key'],
                ['game_id', 'source', 'source_set_id', 'source_url', 'name', 'abbreviation', 'set_code',
                    'set_type', 'language', 'region', 'released_at', 'base_total', 'numbered_total', 'updated_at'],
            );
        }

        $this->line('sets.csv: ' . count($rows) . ' sets');

        return DB::table('binder_sets')->whereNotNull('set_key')->pluck('id', 'set_key')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @param array<string,int> $gameIdBySlug
     * @param array<string,int> $setIdByKey
     * @return array<string,int> card_key => id
     */
    private function importCards(string $path, array $gameIdBySlug, array $setIdByKey): array
    {
        [$header, $handle] = $this->openCsv($path);
        $now = now();
        $rows = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $r = $this->record($header, $row);
            if (! $r || ($r['card_key'] ?? '') === '') {
                continue;
            }

            $gameId = $gameIdBySlug[$r['game_slug'] ?? ''] ?? null;
            $setId = $setIdByKey[$r['set_key'] ?? ''] ?? null;
            if ($gameId === null || $setId === null) {
                $skipped++;

                continue;
            }

            $rows[$r['card_key']] = [
                'set_id' => $setId,
                'game_id' => $gameId,
                'external_product_id' => null,
                'card_key' => $r['card_key'],
                'oracle_id' => $this->nullIfEmpty($r['oracle_id'] ?? null),
                'physical_format_code' => $this->nullIfEmpty($r['physical_format_code'] ?? null),
                'promo_types' => $this->nullIfEmpty($r['promo_types'] ?? null),
                'name' => $r['card_name'] ?? $r['card_key'],
                'clean_name' => $this->nullIfEmpty($r['clean_name'] ?? null),
                'number' => $this->nullIfEmpty($r['collector_number'] ?? null),
                'card_type' => $this->nullIfEmpty($r['card_type'] ?? null),
                'is_promo' => $this->toBool($r['is_promo'] ?? null),
                'is_token' => $this->toBool($r['is_token'] ?? null),
                'language' => $this->nullIfEmpty($r['language'] ?? null) ?? 'EN',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if ($skipped > 0) {
            $this->warn("  cards.csv: skipped {$skipped} rows with unknown game_slug/set_key");
        }

        $rows = array_values($rows);

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('binder_cards')->upsert(
                $chunk,
                ['card_key'],
                ['set_id', 'game_id', 'oracle_id', 'physical_format_code', 'promo_types', 'name', 'clean_name', 'number', 'card_type', 'is_promo', 'is_token', 'language', 'updated_at'],
            );
        }

        $this->line('cards.csv: ' . count($rows) . ' cards');

        return DB::table('binder_cards')->whereNotNull('card_key')->pluck('id', 'card_key')->map(fn ($v) => (int) $v)->all();
    }

    /** @param array<string,int> $cardIdByKey @return array<string,int> variant_key => id */
    private function importVariants(string $path, array $cardIdByKey): array
    {
        [$header, $handle] = $this->openCsv($path);
        $now = now();
        $rows = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $r = $this->record($header, $row);
            if (! $r || ($r['variant_key'] ?? '') === '') {
                continue;
            }

            $cardId = $cardIdByKey[$r['card_key'] ?? ''] ?? null;
            if ($cardId === null) {
                $skipped++;

                continue;
            }

            $rows[$r['variant_key']] = [
                'card_id' => $cardId,
                'variant_key' => $r['variant_key'],
                'variant_name' => $r['variant_name'] ?? 'Normal',
                'variant_type' => $this->nullIfEmpty($r['variant_type'] ?? null),
                'rarity' => $this->nullIfEmpty($r['rarity'] ?? null),
                'region_code' => $this->nullIfEmpty($r['region_code'] ?? null),
                'edition_code' => $this->nullIfEmpty($r['edition_code'] ?? null),
                'artist' => $this->nullIfEmpty($r['artist'] ?? null),
                'image_small' => $this->nullIfEmpty($r['image_small'] ?? null),
                'image_large' => $this->nullIfEmpty($r['image_large'] ?? null),
                'sort_order' => (int) ($r['sort_order'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if ($skipped > 0) {
            $this->warn("  variants.csv: skipped {$skipped} rows with unknown card_key");
        }

        foreach (array_chunk($rows, self::CHUNK, true) as $chunk) {
            DB::table('binder_card_variants')->upsert(
                array_values($chunk),
                ['variant_key'],
                ['card_id', 'variant_name', 'variant_type', 'rarity', 'region_code', 'edition_code', 'artist', 'image_small', 'image_large', 'sort_order', 'updated_at'],
            );
        }

        $this->line('variants.csv: ' . count($rows) . ' variants');

        return DB::table('binder_card_variants')->pluck('id', 'variant_key')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @param array<string,int> $setIdByKey
     * @param array<string,int> $cardIdByKey
     * @param array<string,int> $variantIdByKey
     */
    private function importExternalIds(string $path, array $setIdByKey, array $cardIdByKey, array $variantIdByKey): void
    {
        [$header, $handle] = $this->openCsv($path);
        $now = now();
        $rows = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $r = $this->record($header, $row);
            if (! $r || ($r['provider'] ?? '') === '' || ($r['external_id'] ?? '') === '') {
                continue;
            }

            $entityType = $r['entity_type'] ?? '';
            $entityKey = $r['entity_key'] ?? '';
            $entityExists = match ($entityType) {
                'set' => isset($setIdByKey[$entityKey]),
                'card' => isset($cardIdByKey[$entityKey]),
                'variant' => isset($variantIdByKey[$entityKey]),
                default => false,
            };
            if (! $entityExists) {
                $skipped++;

                continue;
            }

            $rows[] = [
                'entity_type' => $entityType,
                'entity_key' => $entityKey,
                'provider' => $r['provider'],
                'external_id' => $r['external_id'],
                'external_type' => $this->nullIfEmpty($r['external_type'] ?? null),
                'external_url' => $this->nullIfEmpty($r['external_url'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if ($skipped > 0) {
            $this->warn("  external_ids.csv: skipped {$skipped} rows with unknown/unmatched entity_type+entity_key");
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('binder_card_external_ids')->upsert(
                $chunk,
                ['provider', 'external_type', 'external_id'],
                ['entity_type', 'entity_key', 'external_url', 'updated_at'],
            );
        }

        $this->line('external_ids.csv: ' . count($rows) . ' external ids');
    }

    /** @param array<string,int> $gameIdBySlug @return array<string,int> release_key => id */
    private function importReleases(string $path, array $gameIdBySlug): array
    {
        [$header, $handle] = $this->openCsv($path);
        $now = now();
        $rows = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $r = $this->record($header, $row);
            if (! $r || ($r['release_key'] ?? '') === '') {
                continue;
            }

            $gameId = $gameIdBySlug[$r['game_slug'] ?? ''] ?? null;
            if ($gameId === null) {
                $skipped++;

                continue;
            }

            $rows[$r['release_key']] = [
                'game_id' => $gameId,
                'release_key' => $r['release_key'],
                'release_name' => $r['release_name'] ?? $r['release_key'],
                'release_type' => $r['release_type'] ?? 'unknown',
                'region_code' => $this->nullIfEmpty($r['region_code'] ?? null),
                'released_at' => $this->parseDate($r['released_at'] ?? null),
                'source_provider' => $this->nullIfEmpty($r['source_provider'] ?? null),
                'source_external_id' => $this->nullIfEmpty($r['source_external_id'] ?? null),
                'notes' => $this->nullIfEmpty($r['notes'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if ($skipped > 0) {
            $this->warn("  releases.csv: skipped {$skipped} rows with unknown game_slug");
        }

        foreach (array_chunk($rows, self::CHUNK, true) as $chunk) {
            DB::table('binder_releases')->upsert(
                array_values($chunk),
                ['release_key'],
                ['game_id', 'release_name', 'release_type', 'region_code', 'released_at', 'source_provider', 'source_external_id', 'notes', 'updated_at'],
            );
        }

        $this->line('releases.csv: ' . count($rows) . ' releases');

        return DB::table('binder_releases')->pluck('id', 'release_key')->map(fn ($v) => (int) $v)->all();
    }

    /** @param array<string,int> $cardIdByKey @param array<string,int> $releaseIdByKey */
    private function importReleaseMemberships(string $path, array $cardIdByKey, array $releaseIdByKey): void
    {
        [$header, $handle] = $this->openCsv($path);
        $now = now();
        $rows = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $r = $this->record($header, $row);
            if (! $r) {
                continue;
            }

            $cardId = $cardIdByKey[$r['card_key'] ?? ''] ?? null;
            $releaseId = $releaseIdByKey[$r['release_key'] ?? ''] ?? null;
            if ($cardId === null || $releaseId === null) {
                $skipped++;

                continue;
            }

            $rows["{$cardId}:{$releaseId}"] = [
                'card_id' => $cardId,
                'release_id' => $releaseId,
                'membership_type' => $this->nullIfEmpty($r['membership_type'] ?? null),
                'source_provider' => $this->nullIfEmpty($r['source_provider'] ?? null),
                'source_reference' => $this->nullIfEmpty($r['source_reference'] ?? null),
                'notes' => $this->nullIfEmpty($r['notes'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if ($skipped > 0) {
            $this->warn("  release_memberships.csv: skipped {$skipped} rows with unknown card_key/release_key");
        }

        foreach (array_chunk($rows, self::CHUNK, true) as $chunk) {
            DB::table('binder_card_release_memberships')->upsert(
                array_values($chunk),
                ['card_id', 'release_id'],
                ['membership_type', 'source_provider', 'source_reference', 'notes', 'updated_at'],
            );
        }

        $this->line('release_memberships.csv: ' . count($rows) . ' release memberships');
    }

    private function refreshSetCardCounts(array $setIds): void
    {
        if ($setIds === []) {
            return;
        }

        foreach (array_chunk($setIds, self::CHUNK) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            DB::statement(
                "UPDATE binder_sets s
                 SET card_count = (SELECT COUNT(*) FROM binder_cards c WHERE c.set_id = s.id)
                 WHERE s.id IN ({$placeholders})",
                $chunk,
            );
        }
    }

    /** @return array{0: array<int,string>, 1: resource} */
    private function openCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle) ?: [];

        return [$header, $handle];
    }

    /** @param array<int,string> $header @param array<int,string> $row @return array<string,string>|null */
    private function record(array $header, array $row): ?array
    {
        $record = @array_combine($header, $row);

        return $record ?: null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function toBool(?string $value): bool
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'y'], true);
    }

    private function parseDate(?string $value): ?string
    {
        $value = $this->nullIfEmpty($value);
        if ($value === null) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
