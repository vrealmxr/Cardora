<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Dragon Ball Super Card Game: FUSION WORLD ONLY, via the public DeckPlanet
 * API (api.deckplanet.net). Riot^H^H^H Bandai's own official sources
 * (dbs-cardgame.com, bandai-tcg-plus.com) and apitcg.com (no key available,
 * not registered per this session's "no account creation" rule) are both
 * unusable per the source hierarchy set for this game -- DeckPlanet is the
 * fallback and is what this command uses. This command must NEVER touch
 * the `dragon-ball-super-masters` game row -- only `dragon-ball-super-
 * fusion-world`. Confirmed series prefixes here (FB/FS/SB/ST/PR) match
 * exactly the Fusion World classification already established in the
 * 2026-09-25 games-registration pass (BT/DBS-B.. is Masters, FB/FS/SB is
 * Fusion World) -- an independent cross-check that this source and our
 * existing classification agree.
 *
 * Canonical identity = card_number (e.g. "FB05-073"). DeckPlanet already
 * nests variants under their parent via `variant_of`/`variants[]` at the
 * root level, so no guessing is needed -- but `card_number` alone is NOT
 * always a safe variant discriminator (Energy Marker tokens have a nested
 * variant with the *identical* card_number as its parent, distinguished
 * only by `img_link` and the always-unique `id`), so `id` is used as the
 * variant key, same approach as the Riftbound importer.
 */
class ImportDeckplanetFusionWorld extends Command
{
    protected $signature = 'cardora:deckplanet-import-fusionworld
        {--apply : Write to the database. Without this flag, only a dry-run report is produced.}
        {--images : Download and locally cache card images.}
        {--snapshot-dir= : Directory to read/write the frozen raw API snapshot.}';

    protected $description = 'Import the Dragon Ball Super: Fusion World canonical v2 catalog from the DeckPlanet API';

    private const API_BASE = 'https://api.deckplanet.net/cardsearch/fusion_world_cards';
    private const GAME_SLUG = 'dragon-ball-super-fusion-world';
    private const IMAGE_BASE = 'https://dbs-deckplanet.us-southeast-1.linodeobjects.com/deckplanet_card_images/';

    private array $report = [
        'root_cards_fetched' => 0,
        'total_printings_fetched' => 0,
        'cards_written' => 0,
        'variants_written' => 0,
        'external_ids_written' => 0,
        'images_downloaded' => 0,
        'images_deduped' => 0,
        'images_failed' => 0,
        'duplicate_card_keys' => 0,
        'missing_card_number' => 0,
        'unresolved' => [],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $snapshotDir = $this->option('snapshot-dir') ?: storage_path('app/fusionworld-snapshot');
        File::ensureDirectoryExists($snapshotDir);

        $game = BinderGame::query()->where('slug', self::GAME_SLUG)->first();
        if (! $game) {
            $this->error('binder_games row for dragon-ball-super-fusion-world not found.');

            return self::FAILURE;
        }

        $this->info('Fetching full card list...');
        $resp = Http::timeout(60)->get(self::API_BASE, ['limit' => 5000]);
        if (! $resp->ok()) {
            $this->error('Failed to fetch cards: HTTP '.$resp->status());

            return self::FAILURE;
        }
        $body = $resp->json();
        $rootCards = $body['data'];
        File::put($snapshotDir.'/all-cards.json', json_encode($rootCards, JSON_PRETTY_PRINT));
        $this->report['root_cards_fetched'] = count($rootCards);

        $seenVariantKeys = [];
        $bySeriesSet = [];
        $canonicalGroups = [];

        foreach ($rootCards as $root) {
            $number = $root['card_number'] ?? null;
            if (! $number) {
                $this->report['missing_card_number']++;
                $this->report['unresolved'][] = "root card id={$root['id']} has no card_number";
                continue;
            }

            $series = $root['card_series'] ?? 'UNKNOWN';
            $setKey = 'fusionworld-en-'.Str::slug($series);
            $bySeriesSet[$setKey] = $series;

            $cardKey = $setKey.'-'.Str::slug($number);
            if (isset($canonicalGroups[$cardKey])) {
                $this->report['duplicate_card_keys']++;
                $this->report['unresolved'][] = "duplicate card_key {$cardKey} (root id {$root['id']} vs {$canonicalGroups[$cardKey][0]['card']['id']})";
                continue;
            }

            $printings = [$root];
            foreach ($root['variants'] ?? [] as $variant) {
                $this->report['total_printings_fetched']++;
                $printings[] = $variant;
            }
            $this->report['total_printings_fetched']++; // count the root itself too

            $rows = [];
            foreach ($printings as $printing) {
                $variantKey = $cardKey.':'.$printing['id'];
                if (isset($seenVariantKeys[$variantKey])) {
                    $this->report['unresolved'][] = "duplicate variant_key {$variantKey} (should be impossible, id is a PK)";
                    continue;
                }
                $seenVariantKeys[$variantKey] = true;
                $rows[] = ['printing' => $printing, 'variantKey' => $variantKey];
            }

            $canonicalGroups[$cardKey] = [
                'setKey' => $setKey,
                'series' => $series,
                'number' => $number,
                'root' => $root,
                'rows' => $rows,
            ];
        }

        $this->info("Fetched {$this->report['root_cards_fetched']} canonical root cards, {$this->report['total_printings_fetched']} total printings, ".count($bySeriesSet).' sets.');

        if (! $apply) {
            $this->printReport();

            return self::SUCCESS;
        }

        $dbSetIdByKey = [];
        foreach ($bySeriesSet as $setKey => $series) {
            DB::table('binder_sets')->updateOrInsert(
                ['set_key' => $setKey],
                [
                    'game_id' => $game->id,
                    'slug' => $setKey,
                    'source' => 'deckplanet',
                    'source_set_id' => $series,
                    'source_url' => 'https://deckplanet.net',
                    'name' => $series,
                    'abbreviation' => $series,
                    'set_code' => $series,
                    'set_type' => str_contains($series, 'PROMO') || $series === 'PR' ? 'promo' : 'main',
                    'language' => 'EN',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $dbSetIdByKey[$setKey] = DB::table('binder_sets')->where('set_key', $setKey)->value('id');
        }

        foreach ($canonicalGroups as $cardKey => $group) {
            $root = $group['root'];

            $gameplayData = [
                'schema_version' => 1,
                'source' => 'deckplanet',
                'source_version' => 'api.deckplanet.net',
                'resolved_at' => now()->toIso8601String(),
                'provenance' => [],
                'data' => [
                    'card_type' => $root['card_type'] ?? null,
                    'color' => $root['card_color'] ?? null,
                    'energy_cost' => $root['card_energy_cost'] ?? null,
                    'power' => $root['card_power'] ?? null,
                    'combo_power' => $root['card_combo_power'] ?? null,
                    'traits' => $root['card_traits'] ?? [],
                    'skill' => $root['card_skill_unstyled'] ?? null,
                    'keywords' => $root['keywords'] ?? [],
                    'limited_to' => $root['limited_to'] ?? null,
                    'is_banned' => $root['is_banned'] ?? null,
                ],
            ];

            DB::table('binder_cards')->updateOrInsert(
                ['card_key' => $cardKey],
                [
                    'set_id' => $dbSetIdByKey[$group['setKey']],
                    'game_id' => $game->id,
                    'name' => $root['card_name'] ?? '',
                    'clean_name' => $root['card_name'] ?? '',
                    'number' => $group['number'],
                    'rarity' => $root['card_rarity'] ?? null,
                    'card_type' => $root['card_type'] ?? null,
                    'is_promo' => in_array($group['series'], ['PR', 'PROMOTION CARDS'], true),
                    'is_token' => str_contains((string) $root['card_name'], 'Energy Marker'),
                    'language' => 'EN',
                    'gameplay_data' => json_encode($gameplayData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $cardId = DB::table('binder_cards')->where('card_key', $cardKey)->value('id');
            $this->report['cards_written']++;

            $sort = 1;
            foreach ($group['rows'] as $row) {
                $printing = $row['printing'];
                $isBase = $printing['id'] === $root['id'];
                $imgLink = $printing['img_link'] ?? $printing['card_number'];
                $variantSuffix = $this->classifySuffix($imgLink, $root['img_link'] ?? $root['card_number']);

                $imageUrl = self::IMAGE_BASE.$imgLink.'.png';
                $stored = $imageUrl;

                if ($this->option('images')) {
                    $cached = $this->cacheImage($imageUrl, self::GAME_SLUG, $group['setKey'], $cardKey, $row['variantKey'], 'front');
                    if ($cached) {
                        $stored = $cached;
                    }
                }

                DB::table('binder_card_variants')->updateOrInsert(
                    ['variant_key' => $row['variantKey']],
                    [
                        'card_id' => $cardId,
                        'source_variant_id' => (string) $printing['id'],
                        'source_variant_kind' => $isBase ? 'base' : 'alt',
                        'variant_name' => $variantSuffix,
                        'variant_type' => $isBase ? 'base' : Str::slug($variantSuffix),
                        'rarity' => $printing['card_rarity'] ?? null,
                        'image_small' => $stored,
                        'image_large' => $stored,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $this->report['variants_written']++;

                $this->safeUpsertExternalId(
                    ['entity_type' => 'variant', 'entity_key' => $row['variantKey'], 'provider' => 'deckplanet'],
                    ['external_id' => (string) $printing['id'], 'external_type' => 'card_id', 'external_url' => null],
                );
            }
        }

        $this->printReport();

        return self::SUCCESS;
    }

    private function classifySuffix(string $imgLink, string $baseImgLink): string
    {
        if ($imgLink === $baseImgLink) {
            return 'Normal';
        }
        if (preg_match('/_PR(\d*)$/', $imgLink, $m)) {
            return $m[1] === '' ? 'Promo Reprint' : 'Promo Reprint '.$m[1];
        }

        return 'Variant '.$imgLink;
    }

    private function safeUpsertExternalId(array $match, array $values): void
    {
        try {
            DB::table('binder_card_external_ids')->updateOrInsert($match, $values);
            $this->report['external_ids_written']++;
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                $this->report['unresolved'][] = 'external_id collision: '.json_encode($match).' -> '.json_encode($values);

                return;
            }
            throw $e;
        }
    }

    private function cacheImage(string $url, string $game, string $setKey, string $cardKey, string $variantKey, string $side): ?string
    {
        $publicDir = public_path("cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}");
        File::ensureDirectoryExists($publicDir);

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
        $canonicalPath = public_path("cards/_by-hash/{$hash}.png");
        File::ensureDirectoryExists(dirname($canonicalPath));

        if (! File::exists($canonicalPath)) {
            File::put($canonicalPath, $bytes);
            $this->report['images_downloaded']++;
        } else {
            $this->report['images_deduped']++;
        }

        $destPath = $publicDir."/{$side}.png";
        if (! File::exists($destPath)) {
            File::copy($canonicalPath, $destPath);
        }

        return "/cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}/{$side}.png";
    }

    private function printReport(): void
    {
        $this->info('--- Fusion World import report ---');
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
