<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Targeted image backfill for v2 variants that never got an image on their
 * original import. Re-queries the SAME source the canonical importer
 * already uses, for ONLY the specific missing rows -- this is enrichment,
 * not a re-import: canonical identity (card_key/variant_key) is never
 * touched, only image_small/image_large on existing rows.
 *
 * Magic: card_key embeds the Scryfall UUID directly
 * ("magic:scryfall:{uuid}").
 *
 * Pokemon: card_key is a human-readable slug with no embedded id, but the
 * exact TCGdex card id was already captured at import time in
 * binder_card_external_ids (provider=tcgdex, external_type=card_id) --
 * found on closer inspection after an earlier pass wrongly assumed it
 * wasn't stored anywhere.
 *
 * Yu-Gi-Oh!: same story, binder_card_external_ids has the exact
 * YGOPRODeck set_code (provider=ygoprodeck, external_type=set_code). The
 * API has no "look up by set_code" query param, so this fetches the full
 * bulk card list once and matches set_code inside each card's card_sets[]
 * locally, rather than one request per card.
 */
class EnrichMissingCardImages extends Command
{
    protected $signature = 'cardora:enrich-missing-images {game : one of magic-the-gathering|pokemon|yugioh}';

    protected $description = 'Backfill missing images on existing v2 canonical variants from their original source';

    private array $report = ['checked' => 0, 'filled' => 0, 'still_missing' => 0, 'errors' => 0];

    public function handle(): int
    {
        $slug = $this->argument('game');
        $gameId = DB::table('binder_games')->where('slug', $slug)->value('id');
        if (! $gameId) {
            $this->error("Unknown game slug: {$slug}");

            return self::FAILURE;
        }

        $rows = DB::table('binder_card_variants')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_card_variants.card_id')
            ->where('binder_cards.game_id', $gameId)
            ->whereNotNull('binder_cards.card_key')
            ->whereNull('binder_card_variants.image_large')
            ->whereNull('binder_card_variants.image_small')
            ->select('binder_card_variants.id as variant_id', 'binder_card_variants.variant_key', 'binder_card_variants.source_variant_id', 'binder_cards.card_key')
            ->get();

        $this->info("Found {$rows->count()} variants missing images for {$slug}.");

        $ygoSetCodeToImage = $slug === 'yugioh' ? $this->buildYgoSetCodeImageMap() : null;

        foreach ($rows as $row) {
            $this->report['checked']++;

            $imageUrl = match ($slug) {
                'magic-the-gathering' => $this->fetchScryfallImage($row->card_key, $row->source_variant_id),
                'pokemon' => $this->fetchTcgdexImage($row->card_key),
                'yugioh' => $this->lookupYgoImage($row->card_key, $ygoSetCodeToImage),
                default => null,
            };

            if ($imageUrl) {
                DB::table('binder_card_variants')->where('id', $row->variant_id)->update([
                    'image_small' => $imageUrl,
                    'image_large' => $imageUrl,
                    'updated_at' => now(),
                ]);
                $this->report['filled']++;
            } else {
                $this->report['still_missing']++;
            }

            if ($slug !== 'yugioh') {
                usleep(60_000);
            }
        }

        $this->info('--- Enrichment report ---');
        foreach ($this->report as $k => $v) {
            $this->line("{$k}: {$v}");
        }

        return self::SUCCESS;
    }

    private function fetchScryfallImage(string $cardKey, ?string $finish): ?string
    {
        // card_key format: "magic:scryfall:{uuid}"
        $parts = explode(':', $cardKey);
        $id = end($parts);

        try {
            $resp = Http::withHeaders(['User-Agent' => 'CardoraBinderEnrichment/1.0', 'Accept' => '*/*'])
                ->timeout(15)->get("https://api.scryfall.com/cards/{$id}");
        } catch (\Throwable $e) {
            $this->report['errors']++;

            return null;
        }

        if (! $resp->ok()) {
            return null;
        }

        $data = $resp->json();
        $uris = $data['image_uris'] ?? ($data['card_faces'][0]['image_uris'] ?? null);

        return $uris['large'] ?? $uris['normal'] ?? null;
    }

    private function fetchTcgdexImage(string $cardKey): ?string
    {
        $tcgdexId = DB::table('binder_card_external_ids')
            ->where('entity_type', 'card')
            ->where('entity_key', $cardKey)
            ->where('provider', 'tcgdex')
            ->value('external_id');

        if (! $tcgdexId) {
            $this->report['errors']++;

            return null;
        }

        try {
            $resp = Http::timeout(15)->get("https://api.tcgdex.net/v2/en/cards/{$tcgdexId}");
        } catch (\Throwable $e) {
            $this->report['errors']++;

            return null;
        }

        if (! $resp->ok()) {
            return null;
        }

        $image = $resp->json('image');

        return $image ? $image.'/high.png' : null;
    }

    /**
     * @return array<string,string> set_code (uppercased) => image_url
     */
    private function buildYgoSetCodeImageMap(): array
    {
        $this->info('Fetching full YGOPRODeck bulk data to resolve set_codes locally...');

        try {
            $resp = Http::timeout(60)->get('https://db.ygoprodeck.com/api/v7/cardinfo.php');
        } catch (\Throwable $e) {
            $this->error('Failed to fetch YGOPRODeck bulk data: '.$e->getMessage());

            return [];
        }

        $cards = $resp->json('data') ?? [];
        $map = [];
        foreach ($cards as $card) {
            $imageUrl = $card['card_images'][0]['image_url'] ?? null;
            if (! $imageUrl) {
                continue;
            }
            foreach ($card['card_sets'] ?? [] as $set) {
                if (! empty($set['set_code'])) {
                    $map[strtoupper($set['set_code'])] = $imageUrl;
                }
            }
        }

        $this->info('Indexed '.count($map).' set_codes from '.count($cards).' cards.');

        return $map;
    }

    private function lookupYgoImage(string $cardKey, ?array $map): ?string
    {
        if (! $map) {
            return null;
        }

        $setCode = DB::table('binder_card_external_ids')
            ->where('entity_type', 'card')
            ->where('entity_key', $cardKey)
            ->where('provider', 'ygoprodeck')
            ->where('external_type', 'set_code')
            ->value('external_id');

        if (! $setCode) {
            $this->report['errors']++;

            return null;
        }

        return $map[strtoupper($setCode)] ?? null;
    }
}
