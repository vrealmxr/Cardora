<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Riftbound canonical v2 catalog, via the public Riftcodex API
 * (api.riftcodex.com) -- an unofficial fan-run REST API, no key required.
 * Canonical identity is (set_id, collector_number): Riftcodex's own
 * riftbound_id embeds a suffix after the number ("205" base, "205a"
 * Alternate Art, "302*" Signature) for what is otherwise the *same*
 * collector_number -- confirmed directly against the API (Yasuo -
 * Windrider #205 has both a "205" and a "205a" record, both
 * collector_number=205). So the numeric collector_number is the canonical
 * card, and the riftbound_id suffix is the variant axis, same shape as
 * every other game already in the v2 schema.
 */
class ImportRiftcodexRiftbound extends Command
{
    protected $signature = 'cardora:riftcodex-import-riftbound
        {--apply : Write to the database. Without this flag, only a dry-run report is produced.}
        {--images : Download and locally cache card images.}
        {--snapshot-dir= : Directory to read/write the frozen raw API snapshot.}';

    protected $description = 'Import the Riftbound canonical v2 catalog from the Riftcodex API';

    private const API_BASE = 'https://api.riftcodex.com';
    private const GAME_SLUG = 'riftbound';
    private const PAGE_SIZE = 100;

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
        'missing_collector_number' => 0,
        'unresolved' => [],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $snapshotDir = $this->option('snapshot-dir') ?: storage_path('app/riftbound-snapshot');
        File::ensureDirectoryExists($snapshotDir);

        $game = BinderGame::query()->where('slug', self::GAME_SLUG)->first();
        if (! $game) {
            $this->error('binder_games row for riftbound not found.');

            return self::FAILURE;
        }

        $this->info('Fetching set list...');
        $setsResponse = Http::timeout(30)->get(self::API_BASE.'/sets');
        if (! $setsResponse->ok()) {
            $this->error('Failed to fetch sets: HTTP '.$setsResponse->status());

            return self::FAILURE;
        }
        $sets = $setsResponse->json('items');
        File::put($snapshotDir.'/sets.json', json_encode($sets, JSON_PRETTY_PRINT));
        $this->report['sets_fetched'] = count($sets);

        $seenCardKeys = [];
        $allCards = [];

        foreach ($sets as $set) {
            $setId = $set['set_id'];
            $setKey = 'riftbound-en-'.Str::slug($setId);
            $dbSetId = null;

            if ($apply) {
                DB::table('binder_sets')->updateOrInsert(
                    ['set_key' => $setKey],
                    [
                        'game_id' => $game->id,
                        'slug' => $setKey,
                        'source' => 'riftcodex',
                        'source_set_id' => $set['id'],
                        'source_url' => 'https://riftcodex.com',
                        'name' => $set['name'],
                        'abbreviation' => $setId,
                        'set_code' => $setId,
                        'set_type' => 'main',
                        'language' => 'EN',
                        'released_at' => $set['published_on'] ?? null,
                        'card_count' => $set['card_count'] ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $dbSetId = DB::table('binder_sets')->where('set_key', $setKey)->value('id');
            }

            $page = 1;
            $totalPages = 1;
            do {
                usleep(80_000);
                $resp = Http::timeout(30)->get(self::API_BASE.'/cards', [
                    'set_id' => $setId,
                    'size' => self::PAGE_SIZE,
                    'page' => $page,
                ]);
                if (! $resp->ok()) {
                    $this->report['unresolved'][] = "set {$setId} page {$page}: fetch failed HTTP {$resp->status()}";
                    break;
                }
                $body = $resp->json();
                $totalPages = $body['pages'] ?? 1;
                $cards = $body['items'] ?? [];
                File::put($snapshotDir."/set-{$setId}-page-{$page}.json", json_encode($cards, JSON_PRETTY_PRINT));
                $this->report['cards_fetched'] += count($cards);

                foreach ($cards as $card) {
                    $number = $card['collector_number'] ?? null;
                    if ($number === null || $number === '') {
                        $this->report['missing_collector_number']++;
                        $this->report['unresolved'][] = "set {$setId}: card '{$card['name']}' has no collector_number";
                        continue;
                    }

                    $riftboundId = $card['riftbound_id'] ?? '';
                    $recordId = $card['id'] ?? null;
                    if (! $riftboundId || ! $recordId) {
                        $this->report['unresolved'][] = "set {$setId}: card '{$card['name']}' missing riftbound_id or id";
                        continue;
                    }

                    // riftbound_id is NOT always a reliable (number, total)
                    // pair to parse -- confirmed against the live API that
                    // its embedded number can literally disagree with the
                    // card's own collector_number field (e.g. "opp-014-024"
                    // has collector_number=6), and promo sets like OPP reuse
                    // collector_number across genuinely different cards with
                    // different trailing totals. So riftbound_id is used
                    // whole, as the source's own grouping key, not parsed.
                    //
                    // Two known real collision patterns on top of that,
                    // confirmed by inspecting every duplicate group in the
                    // live dataset (147 groups checked by hand):
                    // 1. A premium finish (name suffixed "(Metal)") shares
                    //    the exact same riftbound_id as its standard
                    //    counterpart -- these are genuine finish variants of
                    //    ONE canonical card, so they're allowed to collide
                    //    into the same card_key and become two variants.
                    // 2. An "(Overnumbered)" reprint (metadata.overnumbered
                    //    or the literal string in the name) shares a
                    //    riftbound_id with an unrelated card that happens to
                    //    reuse the same number -- these are NOT the same
                    //    canonical card, so they're split out using the
                    //    record's own always-unique `id` instead.
                    $isOvernumbered = ($card['metadata']['overnumbered'] ?? false)
                        || str_contains((string) $card['name'], '(Overnumbered)');

                    $cardKey = $isOvernumbered
                        ? $setKey.'-ovn-'.$recordId
                        : $setKey.'-'.Str::slug($riftboundId);

                    // The record's own id is always unique, so it alone is
                    // sufficient (and correct) as the variant discriminator
                    // -- no need to also parse a finish/suffix out of the
                    // string for uniqueness purposes.
                    $variantKey = $cardKey.':'.$recordId;
                    if (isset($seenCardKeys[$variantKey])) {
                        // Should be structurally impossible (recordId is
                        // unique), kept only as a hard-fail tripwire.
                        $this->report['duplicate_card_keys']++;
                        $this->report['unresolved'][] = "duplicate variant_key {$variantKey}";
                        continue;
                    }
                    $seenCardKeys[$variantKey] = true;

                    $suffix = $this->classifyVariant($card, $riftboundId, (string) $number);

                    $allCards[] = compact('card', 'setKey', 'setId', 'number', 'cardKey', 'variantKey', 'suffix', 'dbSetId', 'isOvernumbered');
                }

                $page++;
            } while ($page <= $totalPages);
        }

        $this->info("Fetched {$this->report['sets_fetched']} sets, {$this->report['cards_fetched']} cards.");

        if (! $apply) {
            $this->printReport();

            return self::SUCCESS;
        }

        // Group by cardKey (base canonical identity) so we write one
        // binder_cards row per canonical card, with N variant rows.
        $byCardKey = [];
        foreach ($allCards as $row) {
            $byCardKey[$row['cardKey']][] = $row;
        }

        foreach ($byCardKey as $cardKey => $rows) {
            // Prefer the "base" (non-suffixed) record for the canonical
            // card's own name/text/attributes; fall back to the first
            // variant record if a card only exists as e.g. Signature.
            $baseRow = null;
            foreach ($rows as $r) {
                if ($r['suffix']['id'] === 'base') {
                    $baseRow = $r;
                    break;
                }
            }
            $baseRow ??= $rows[0];
            $baseCard = $baseRow['card'];

            $gameplayData = [
                'schema_version' => 1,
                'source' => 'riftcodex',
                'source_version' => 'api.riftcodex.com',
                'resolved_at' => now()->toIso8601String(),
                'provenance' => [],
                'data' => [
                    'type' => $baseCard['classification']['type'] ?? null,
                    'supertype' => $baseCard['classification']['supertype'] ?? null,
                    'domain' => $baseCard['classification']['domain'] ?? [],
                    'energy' => $baseCard['attributes']['energy'] ?? null,
                    'might' => $baseCard['attributes']['might'] ?? null,
                    'power' => $baseCard['attributes']['power'] ?? null,
                    'text' => $baseCard['text']['plain'] ?? null,
                    'flavour' => $baseCard['text']['flavour'] ?? null,
                    'tags' => $baseCard['tags'] ?? [],
                ],
            ];

            DB::table('binder_cards')->updateOrInsert(
                ['card_key' => $cardKey],
                [
                    'set_id' => $baseRow['dbSetId'],
                    'game_id' => $game->id,
                    'name' => $baseCard['name'],
                    'clean_name' => $baseCard['metadata']['clean_name'] ?? $baseCard['name'],
                    'number' => (string) $baseRow['number'],
                    'rarity' => $baseCard['classification']['rarity'] ?? null,
                    'card_type' => $baseCard['classification']['type'] ?? null,
                    'is_promo' => in_array($baseRow['setId'], ['PR', 'OPP', 'JDG'], true),
                    'is_token' => false,
                    'language' => 'EN',
                    'gameplay_data' => json_encode($gameplayData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $cardId = DB::table('binder_cards')->where('card_key', $cardKey)->value('id');
            $this->report['cards_written']++;

            if (! empty($baseCard['tcgplayer_id'])) {
                $this->safeUpsertExternalId(
                    ['entity_type' => 'card', 'entity_key' => $cardKey, 'provider' => 'tcgplayer'],
                    ['external_id' => (string) $baseCard['tcgplayer_id'], 'external_type' => 'product_id', 'external_url' => null],
                );
            }

            $sort = 1;
            foreach ($rows as $row) {
                $card = $row['card'];
                $imageUrl = $card['media']['image_url'] ?? null;
                $stored = $imageUrl;

                if ($this->option('images') && $imageUrl) {
                    $cached = $this->cacheImage($imageUrl, self::GAME_SLUG, $row['setKey'], $cardKey, $row['variantKey'], 'front');
                    if ($cached) {
                        $stored = $cached;
                    }
                }

                DB::table('binder_card_variants')->updateOrInsert(
                    ['variant_key' => $row['variantKey']],
                    [
                        'card_id' => $cardId,
                        'source_variant_id' => $card['riftbound_id'] ?? $row['suffix']['id'],
                        'source_variant_kind' => $row['suffix']['id'] === 'base' ? 'base' : 'alt',
                        'variant_name' => $row['suffix']['name'],
                        'variant_type' => $row['suffix']['id'],
                        'rarity' => $card['classification']['rarity'] ?? null,
                        'artist' => $card['media']['artist'] ?? null,
                        'image_small' => $stored,
                        'image_large' => $stored,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $this->report['variants_written']++;

                $this->safeUpsertExternalId(
                    ['entity_type' => 'variant', 'entity_key' => $row['variantKey'], 'provider' => 'riftcodex'],
                    ['external_id' => $card['id'], 'external_type' => 'card_id', 'external_url' => null],
                );
            }
        }

        $this->printReport();

        return self::SUCCESS;
    }

    /**
     * riftbound_id shape: "{set}-{number}{suffix}-{total}". suffix is "" for
     * base, "a" for Alternate Art, "*" for Signature -- confirmed against
     * the API's own metadata flags (alternate_art / signature booleans)
     * rather than guessed from the string alone.
     */
    /**
     * Classify a printing's finish/variant using the API's own explicit
     * metadata flags and name markers, in priority order -- never inferred
     * from parsing the riftbound_id string alone (shown unreliable above).
     */
    private function classifyVariant(array $card, string $riftboundId, string $number): array
    {
        $name = (string) ($card['name'] ?? '');
        $meta = $card['metadata'] ?? [];

        if (($meta['signature'] ?? false) || str_contains($name, '(Signature)')) {
            return ['id' => 'signature', 'name' => 'Signature'];
        }
        if (str_contains($name, '(Metal)')) {
            return ['id' => 'metal', 'name' => 'Metal'];
        }
        if (($meta['alternate_art'] ?? false) || str_contains($name, '(Alternate Art)')) {
            return ['id' => 'alternate_art', 'name' => 'Alternate Art'];
        }
        if (($meta['overnumbered'] ?? false) || str_contains($name, '(Overnumbered)')) {
            return ['id' => 'overnumbered', 'name' => 'Overnumbered Reprint'];
        }

        return ['id' => 'base', 'name' => 'Normal'];
    }

    /**
     * binder_card_external_ids enforces (provider, external_type,
     * external_id) uniqueness -- confirmed on real data that a single
     * TCGplayer product listing can be the marketplace's ID for what
     * Riftcodex treats as two distinct canonical cards (e.g. base vs an
     * overnumbered reprint TCGplayer never split into a separate SKU).
     * That's a genuine "marketplace product ID != canonical card ID"
     * collision, not a bug -- record it as unresolved and keep going
     * rather than losing the whole import to one duplicate key.
     */
    private function safeUpsertExternalId(array $match, array $values): void
    {
        try {
            DB::table('binder_card_external_ids')->updateOrInsert($match, $values);
            $this->report['external_ids_written']++;
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                $this->report['unresolved'][] = 'external_id collision (marketplace ID shared across canonical cards): '
                    .json_encode($match).' -> '.json_encode($values);

                return;
            }
            throw $e;
        }
    }

    private function cacheImage(string $url, string $game, string $setKey, string $cardKey, string $variantKey, string $side): ?string
    {
        $publicDir = public_path("cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}");
        File::ensureDirectoryExists($publicDir);

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $destPath = $publicDir."/{$side}.{$ext}";

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

        if (! File::exists($destPath)) {
            File::copy($canonicalPath, $destPath);
        }

        return "/cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}/{$side}.{$ext}";
    }

    private function printReport(): void
    {
        $this->info('--- Riftbound import report ---');
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
