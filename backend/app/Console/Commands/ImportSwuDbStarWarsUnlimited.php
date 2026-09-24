<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Star Wars: Unlimited canonical v2 catalog, via the public SWU-DB API
 * (api.swu-db.com) -- community-run, no key. This is a DIFFERENT game from
 * `star-wars-miniatures` (WizKids' 2004-2010 game already in the catalog
 * under a separate binder_games row); this command only ever touches
 * `star-wars-unlimited`.
 *
 * The /cards/{set} listing endpoint returns exactly one record per
 * (Set, Number) -- confirmed on TWI (257 cards -> 257 unique Numbers ->
 * 257 unique cids). It does NOT return every premium-finish printing of a
 * card as separate rows; VariantType on the returned record just labels
 * what edition *that* record itself is (usually "Normal", but some
 * promo-only cards only exist as e.g. "Hyperspace" with no plain version).
 * So: the fetched record's own VariantType becomes its own variant, and a
 * second "Foil" variant is added only when FoilPrice is present -- no
 * Hyperspace/Showcase variant is fabricated for a card that wasn't
 * actually returned as one, since the API gives no mapping to invent that
 * safely.
 */
class ImportSwuDbStarWarsUnlimited extends Command
{
    protected $signature = 'cardora:swudb-import-starwarsunlimited
        {--apply : Write to the database. Without this flag, only a dry-run report is produced.}
        {--images : Download and locally cache card images (front + back where DoubleSided).}
        {--snapshot-dir= : Directory to read/write the frozen raw API snapshot.}';

    protected $description = 'Import the Star Wars: Unlimited canonical v2 catalog from the SWU-DB API';

    private const API_BASE = 'https://api.swu-db.com';
    private const GAME_SLUG = 'star-wars-unlimited';

    private array $report = [
        'sets_fetched' => 0,
        'cards_fetched' => 0,
        'cards_written' => 0,
        'variants_written' => 0,
        'external_ids_written' => 0,
        'images_downloaded' => 0,
        'images_deduped' => 0,
        'images_failed' => 0,
        'duplicate_card_keys' => 0,
        'missing_number' => 0,
        'unresolved' => [],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $snapshotDir = $this->option('snapshot-dir') ?: storage_path('app/swu-snapshot');
        File::ensureDirectoryExists($snapshotDir);

        $game = BinderGame::query()->where('slug', self::GAME_SLUG)->first();
        if (! $game) {
            $this->error('binder_games row for star-wars-unlimited not found.');

            return self::FAILURE;
        }

        $this->info('Fetching set list...');
        $setsResp = Http::timeout(30)->get(self::API_BASE.'/sets');
        if (! $setsResp->ok()) {
            $this->error('Failed to fetch sets: HTTP '.$setsResp->status());

            return self::FAILURE;
        }
        $sets = $setsResp->json();
        File::put($snapshotDir.'/sets.json', json_encode($sets, JSON_PRETTY_PRINT));
        $this->report['sets_fetched'] = count($sets);

        $seenCardKeys = [];
        $allCards = [];

        foreach ($sets as $set) {
            $setId = $set['setId'];
            $setKey = 'swu-en-'.Str::slug($setId);
            usleep(80_000);

            $resp = Http::timeout(30)->get(self::API_BASE."/cards/{$setId}");
            if (! $resp->ok()) {
                $this->report['unresolved'][] = "set {$setId}: fetch failed HTTP {$resp->status()}";
                continue;
            }
            $body = $resp->json();
            $cards = $body['data'] ?? [];
            File::put($snapshotDir."/set-{$setId}.json", json_encode($cards, JSON_PRETTY_PRINT));
            $this->report['cards_fetched'] += count($cards);

            $dbSetId = null;
            if ($apply) {
                DB::table('binder_sets')->updateOrInsert(
                    ['set_key' => $setKey],
                    [
                        'game_id' => $game->id,
                        'slug' => $setKey,
                        'source' => 'swu-db',
                        'source_set_id' => $setId,
                        'source_url' => 'https://swu-db.com',
                        'name' => $set['fullName'],
                        'abbreviation' => $setId,
                        'set_code' => $setId,
                        'set_type' => $set['isBaseSet'] ?? false ? 'main' : 'promo',
                        'language' => 'EN',
                        'released_at' => $this->parseDate($set['releaseDate'] ?? null),
                        'card_count' => $set['numberCards'] ?? count($cards),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $dbSetId = DB::table('binder_sets')->where('set_key', $setKey)->value('id');
            }

            foreach ($cards as $card) {
                $number = $card['Number'] ?? null;
                if ($number === null || $number === '') {
                    $this->report['missing_number']++;
                    $this->report['unresolved'][] = "set {$setId}: card '{$card['Name']}' has no Number";
                    continue;
                }

                $cardKey = $setKey.'-'.Str::slug($number);
                if (isset($seenCardKeys[$cardKey])) {
                    $this->report['duplicate_card_keys']++;
                    $this->report['unresolved'][] = "duplicate card_key {$cardKey}";
                    continue;
                }
                $seenCardKeys[$cardKey] = true;

                $allCards[] = compact('card', 'setKey', 'setId', 'number', 'cardKey', 'dbSetId');
            }
        }

        $this->info("Fetched {$this->report['sets_fetched']} sets, {$this->report['cards_fetched']} cards.");

        if (! $apply) {
            $this->printReport();

            return self::SUCCESS;
        }

        foreach ($allCards as $row) {
            $card = $row['card'];
            $ownVariantType = $card['VariantType'] ?? 'Normal';

            $gameplayData = [
                'schema_version' => 1,
                'source' => 'swu-db',
                'source_version' => 'api.swu-db.com',
                'resolved_at' => now()->toIso8601String(),
                'provenance' => [],
                'data' => [
                    'type' => $card['Type'] ?? null,
                    'aspects' => $card['Aspects'] ?? [],
                    'traits' => $card['Traits'] ?? [],
                    'arenas' => $card['Arenas'] ?? [],
                    'cost' => $card['Cost'] ?? null,
                    'power' => $card['Power'] ?? null,
                    'hp' => $card['HP'] ?? null,
                    'front_text' => $card['FrontText'] ?? null,
                    'back_text' => $card['BackText'] ?? null,
                    'epic_action' => $card['EpicAction'] ?? null,
                    'keywords' => $card['Keywords'] ?? [],
                    'unique' => $card['Unique'] ?? null,
                ],
            ];

            DB::table('binder_cards')->updateOrInsert(
                ['card_key' => $row['cardKey']],
                [
                    'set_id' => $row['dbSetId'],
                    'game_id' => $game->id,
                    'name' => trim(($card['Name'] ?? '').(isset($card['Subtitle']) ? ', '.$card['Subtitle'] : '')),
                    'clean_name' => $card['Name'] ?? '',
                    'number' => $row['number'],
                    'rarity' => $card['Rarity'] ?? null,
                    'card_type' => $card['Type'] ?? null,
                    'is_promo' => ! ($card['DoubleSided'] ?? false) && str_contains($row['setId'], 'P'),
                    'is_token' => false,
                    'language' => 'EN',
                    'gameplay_data' => json_encode($gameplayData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $cardId = DB::table('binder_cards')->where('card_key', $row['cardKey'])->value('id');
            $this->report['cards_written']++;

            if (! empty($card['tcgplayerId'])) {
                $this->safeUpsertExternalId(
                    ['entity_type' => 'card', 'entity_key' => $row['cardKey'], 'provider' => 'tcgplayer'],
                    ['external_id' => (string) $card['tcgplayerId'], 'external_type' => 'product_id', 'external_url' => null],
                );
            }
            $this->safeUpsertExternalId(
                ['entity_type' => 'card', 'entity_key' => $row['cardKey'], 'provider' => 'swu-db'],
                ['external_id' => (string) $card['cid'], 'external_type' => 'cid', 'external_url' => null],
            );

            $variants = [
                ['id' => Str::slug($ownVariantType), 'name' => $ownVariantType, 'kind' => 'base'],
            ];
            if (! empty($card['FoilPrice']) && $ownVariantType === 'Normal') {
                $variants[] = ['id' => 'foil', 'name' => 'Foil', 'kind' => 'foil'];
            }

            $sort = 1;
            foreach ($variants as $variant) {
                $variantKey = $row['cardKey'].':'.$variant['id'];
                $frontImage = $card['FrontArt'] ?? null;
                $backImage = $card['BackArt'] ?? null;

                if ($this->option('images')) {
                    if ($frontImage) {
                        $cached = $this->cacheImage($frontImage, self::GAME_SLUG, $row['setKey'], $row['cardKey'], $variantKey, 'front');
                        if ($cached) {
                            $frontImage = $cached;
                        }
                    }
                    if ($backImage) {
                        $cached = $this->cacheImage($backImage, self::GAME_SLUG, $row['setKey'], $row['cardKey'], $variantKey, 'back');
                        if ($cached) {
                            $backImage = $cached;
                        }
                    }
                }

                DB::table('binder_card_variants')->updateOrInsert(
                    ['variant_key' => $variantKey],
                    [
                        'card_id' => $cardId,
                        'source_variant_id' => $variant['id'],
                        'source_variant_kind' => $variant['kind'],
                        'variant_name' => $variant['name'],
                        'variant_type' => $variant['kind'],
                        'rarity' => $card['Rarity'] ?? null,
                        'artist' => $card['Artist'] ?? null,
                        'image_small' => $frontImage,
                        'image_large' => $frontImage,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $this->report['variants_written']++;

                if ($backImage) {
                    // Back-face art has no dedicated column in
                    // binder_card_variants -- stored as a card-scoped
                    // external "asset" reference rather than losing it.
                    $this->safeUpsertExternalId(
                        ['entity_type' => 'variant', 'entity_key' => $variantKey, 'provider' => 'swu-db-back-image'],
                        ['external_id' => $variantKey, 'external_type' => 'back_image_url', 'external_url' => $backImage],
                    );
                }
            }
        }

        $this->printReport();

        return self::SUCCESS;
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return \Carbon\Carbon::createFromFormat('n/j/y', $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
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
        $this->info('--- Star Wars: Unlimited import report ---');
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
