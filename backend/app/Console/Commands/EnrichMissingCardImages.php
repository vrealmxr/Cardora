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
 * Magic only, for now: its card_key embeds the actual Scryfall UUID
 * ("magic:scryfall:{uuid}"), so the exact source record can be re-fetched
 * directly. Pokemon's and Yu-Gi-Oh!'s card_keys are human-readable slugs
 * (e.g. "pokemon-2011bw-1", "yugioh-2-player-starter-deck-...-end00") with
 * no embedded TCGdex/YGOPRODeck id and a null source_variant_id on the
 * rows checked -- there's no reliable way to reverse a slug back to the
 * source's real id without risking a wrong match, so those two are left
 * as a known, still-open backlog item rather than guessed at.
 */
class EnrichMissingCardImages extends Command
{
    protected $signature = 'cardora:enrich-missing-images {game : one of magic-the-gathering}';

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

        foreach ($rows as $row) {
            $this->report['checked']++;

            $imageUrl = match ($slug) {
                'magic-the-gathering' => $this->fetchScryfallImage($row->card_key, $row->source_variant_id),
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

            usleep(60_000);
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

}
