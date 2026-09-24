<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Disney Lorcana canonical v2 catalog, via the public Lorcast API
 * (api.lorcast.com/v0) -- no API key, JSON, English-only per Cardora's
 * current scope. Mirrors the identity/variant/external-id conventions
 * already established by ImportOnePiece/ImportYgoprodeckYugioh: canonical
 * card = (set, collector_number); a card's own printed rarity of
 * "Enchanted"/"Iconic" is itself a distinct collector_number in Lorcast's
 * numbering (not a foil flag on a lower-numbered base card), so no special
 * casing is needed for those -- they just import as their own card, same
 * as everything else. Finish (Normal/Foil) is the only per-printing
 * variant axis Lorcast exposes, inferred from which of prices.usd /
 * prices.usd_foil are present.
 */
class ImportLorcastLorcana extends Command
{
    protected $signature = 'cardora:lorcast-import-lorcana
        {--apply : Write to the database. Without this flag, only a dry-run report is produced.}
        {--images : Download and locally cache card images (front only; Lorcast has no back images).}
        {--snapshot-dir= : Directory to read/write the frozen raw API snapshot (default: storage/app/lorcana-snapshot)}';

    protected $description = 'Import the Disney Lorcana canonical v2 catalog from the Lorcast API';

    private const API_BASE = 'https://api.lorcast.com/v0';
    private const GAME_SLUG = 'disney-lorcana';

    private array $report = [
        'sets_fetched' => 0,
        'cards_fetched' => 0,
        'cards_written' => 0,
        'variants_written' => 0,
        'external_ids_written' => 0,
        'images_downloaded' => 0,
        'images_deduped' => 0,
        'images_failed' => 0,
        'images_skipped_existing' => 0,
        'duplicate_card_keys' => 0,
        'missing_collector_number' => 0,
        'unresolved' => [],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $snapshotDir = $this->option('snapshot-dir') ?: storage_path('app/lorcana-snapshot');
        File::ensureDirectoryExists($snapshotDir);

        $game = BinderGame::query()->where('slug', self::GAME_SLUG)->first();
        if (! $game) {
            $this->error('binder_games row for disney-lorcana not found.');

            return self::FAILURE;
        }

        $this->info('Fetching set list...');
        $setsResponse = Http::timeout(30)->get(self::API_BASE.'/sets');
        if (! $setsResponse->ok()) {
            $this->error('Failed to fetch sets: HTTP '.$setsResponse->status());

            return self::FAILURE;
        }
        $sets = $setsResponse->json('results');
        File::put($snapshotDir.'/sets.json', json_encode($sets, JSON_PRETTY_PRINT));
        $this->report['sets_fetched'] = count($sets);

        $seenCardKeys = [];
        $allCards = [];

        foreach ($sets as $set) {
            $code = $set['code'];
            usleep(80_000);
            $resp = Http::timeout(30)->get(self::API_BASE."/sets/{$code}/cards");
            if (! $resp->ok()) {
                $this->report['unresolved'][] = "set {$code}: fetch failed HTTP {$resp->status()}";
                continue;
            }
            $cards = $resp->json();
            File::put($snapshotDir."/set-{$code}.json", json_encode($cards, JSON_PRETTY_PRINT));
            $this->report['cards_fetched'] += count($cards);

            $setKey = 'lorcana-en-'.Str::slug($code);
            $setId = null;

            if ($apply) {
                DB::table('binder_sets')->updateOrInsert(
                    ['set_key' => $setKey],
                    [
                        'game_id' => $game->id,
                        'slug' => $setKey,
                        'source' => 'lorcast',
                        'source_set_id' => $set['id'],
                        'source_url' => 'https://lorcast.com',
                        'name' => $set['name'],
                        'abbreviation' => $code,
                        'set_code' => $code,
                        'set_type' => 'main',
                        'language' => 'EN',
                        'released_at' => $set['released_at'] ?? null,
                        'card_count' => count($cards),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $setId = DB::table('binder_sets')->where('set_key', $setKey)->value('id');
            }

            foreach ($cards as $card) {
                $number = $card['collector_number'] ?? null;
                if ($number === null || $number === '') {
                    $this->report['missing_collector_number']++;
                    $this->report['unresolved'][] = "set {$code}: card '{$card['name']}' has no collector_number";
                    continue;
                }

                $cardKey = $setKey.'-'.Str::slug($number);
                if (isset($seenCardKeys[$cardKey])) {
                    $this->report['duplicate_card_keys']++;
                    $this->report['unresolved'][] = "duplicate card_key {$cardKey} (set {$code} #{$number})";
                    continue;
                }
                $seenCardKeys[$cardKey] = true;

                $allCards[] = compact('card', 'setKey', 'code', 'number', 'cardKey', 'setId');
            }
        }

        $this->info("Fetched {$this->report['sets_fetched']} sets, {$this->report['cards_fetched']} cards.");

        if (! $apply) {
            $this->printReport();

            return self::SUCCESS;
        }

        foreach ($allCards as $row) {
            $card = $row['card'];
            $title = trim(($card['name'] ?? '').(isset($card['version']) ? ' - '.$card['version'] : ''));
            $hasNormal = isset($card['prices']['usd']);
            $hasFoil = isset($card['prices']['usd_foil']);
            if (! $hasNormal && ! $hasFoil) {
                // Lorcast sometimes has no price data yet (very recent releases);
                // still import the card with a single "Normal" variant so it's not silently dropped.
                $hasNormal = true;
            }

            $gameplayData = [
                'schema_version' => 1,
                'source' => 'lorcast',
                'source_version' => 'api.lorcast.com/v0',
                'resolved_at' => now()->toIso8601String(),
                'provenance' => [],
                'data' => [
                    'type' => $card['type'] ?? [],
                    'ink' => $card['ink'] ?? null,
                    'inks' => $card['inks'] ?? null,
                    'cost' => $card['cost'] ?? null,
                    'inkwell' => $card['inkwell'] ?? null,
                    'strength' => $card['strength'] ?? null,
                    'willpower' => $card['willpower'] ?? null,
                    'lore' => $card['lore'] ?? null,
                    'move_cost' => $card['move_cost'] ?? null,
                    'classifications' => $card['classifications'] ?? [],
                    'keywords' => $card['keywords'] ?? [],
                    'text' => $card['text'] ?? null,
                    'flavor_text' => $card['flavor_text'] ?? null,
                    'legalities' => $card['legalities'] ?? null,
                ],
            ];

            DB::table('binder_cards')->updateOrInsert(
                ['card_key' => $row['cardKey']],
                [
                    'set_id' => $row['setId'],
                    'game_id' => $game->id,
                    'name' => $title,
                    'clean_name' => $card['name'] ?? $title,
                    'number' => $row['number'],
                    'rarity' => $card['rarity'] ?? null,
                    'card_type' => is_array($card['type'] ?? null) ? implode('/', $card['type']) : null,
                    'is_promo' => str_starts_with($row['code'], 'P') || $row['code'] === 'cp',
                    'is_token' => false,
                    'language' => 'EN',
                    'gameplay_data' => json_encode($gameplayData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $cardId = DB::table('binder_cards')->where('card_key', $row['cardKey'])->value('id');
            $this->report['cards_written']++;

            if (! empty($card['tcgplayer_id'])) {
                DB::table('binder_card_external_ids')->updateOrInsert(
                    ['entity_type' => 'card', 'entity_key' => $row['cardKey'], 'provider' => 'tcgplayer'],
                    [
                        'external_id' => (string) $card['tcgplayer_id'],
                        'external_type' => 'product_id',
                        'external_url' => $card['purchase_uris']['tcgplayer'] ?? null,
                    ],
                );
                $this->report['external_ids_written']++;
            }

            DB::table('binder_card_external_ids')->updateOrInsert(
                ['entity_type' => 'card', 'entity_key' => $row['cardKey'], 'provider' => 'lorcast'],
                [
                    'external_id' => $card['id'],
                    'external_type' => 'card_id',
                    'external_url' => null,
                ],
            );
            $this->report['external_ids_written']++;

            $finishes = [];
            if ($hasNormal) {
                $finishes[] = ['id' => 'normal', 'name' => 'Normal', 'type' => 'base'];
            }
            if ($hasFoil) {
                $finishes[] = ['id' => 'foil', 'name' => 'Foil', 'type' => 'foil'];
            }

            $sort = 1;
            foreach ($finishes as $finish) {
                $variantKey = $row['cardKey'].':'.$finish['id'];
                $imageLarge = $card['image_uris']['digital']['large'] ?? null;
                $imageSmall = $card['image_uris']['digital']['small'] ?? null;

                if ($this->option('images') && $imageLarge) {
                    $cached = $this->cacheImage($imageLarge, self::GAME_SLUG, $row['setKey'], $row['cardKey'], $variantKey, 'front');
                    if ($cached) {
                        $imageLarge = $cached;
                    }
                }

                DB::table('binder_card_variants')->updateOrInsert(
                    ['variant_key' => $variantKey],
                    [
                        'card_id' => $cardId,
                        'source_variant_id' => $finish['id'],
                        'source_variant_kind' => $finish['type'],
                        'variant_name' => $finish['name'],
                        'variant_type' => $finish['type'],
                        'rarity' => $card['rarity'] ?? null,
                        'artist' => is_array($card['illustrators'] ?? null) ? implode(', ', $card['illustrators']) : null,
                        'image_small' => $imageSmall,
                        'image_large' => $imageLarge,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $this->report['variants_written']++;
            }
        }

        $this->printReport();

        return self::SUCCESS;
    }

    private function cacheImage(string $url, string $game, string $setKey, string $cardKey, string $variantKey, string $side): ?string
    {
        $publicDir = public_path("cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}");
        File::ensureDirectoryExists($publicDir);

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $tmpPath = $publicDir."/{$side}.{$ext}";

        try {
            $bytes = Http::timeout(20)->get($url)->body();
        } catch (\Throwable $e) {
            $this->report['images_failed']++;

            return null;
        }

        if (! $bytes) {
            $this->report['images_failed']++;

            return null;
        }

        $hash = hash('sha256', $bytes);
        $canonicalPath = public_path("cards/_by-hash/{$hash}.{$ext}");
        File::ensureDirectoryExists(dirname($canonicalPath));

        if (! File::exists($canonicalPath)) {
            File::put($canonicalPath, $bytes);
            $this->report['images_downloaded']++;
        } else {
            $this->report['images_deduped']++;
        }

        // The per-card path is a relative symlink-equivalent: since shared
        // hosting may not allow symlinks reliably, we store the same bytes
        // at the descriptive path too (cheap: local disk copy, not a
        // second network fetch) so URLs stay human-readable.
        if (! File::exists($tmpPath)) {
            File::copy($canonicalPath, $tmpPath);
        }

        return "/cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}/{$side}.{$ext}";
    }

    private function printReport(): void
    {
        $this->info('--- Lorcana import report ---');
        foreach ($this->report as $key => $value) {
            if ($key === 'unresolved') {
                continue;
            }
            $this->line("{$key}: ".$value);
        }
        if (! empty($this->report['unresolved'])) {
            $this->warn('Unresolved ('.count($this->report['unresolved']).'):');
            foreach (array_slice($this->report['unresolved'], 0, 30) as $line) {
                $this->line('  - '.$line);
            }
        }
    }
}
