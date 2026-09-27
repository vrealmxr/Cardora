<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Backfills binder_sets.image_url -- a set-level cover image (set icon /
 * logo / box art), distinct from any card's own image -- for the 3 games
 * whose source actually publishes one:
 *
 * Magic (Scryfall): every set has a small monochrome "set symbol" SVG,
 * bulk-fetched in a single `GET /sets` call, keyed by set_code.
 *
 * Pokemon (TCGdex): most sets have a real multi-color logo image, fetched
 * per-set (`GET /sets/{id}`) since TCGdex has no bulk sets-with-logo
 * endpoint, keyed by source_set_id (the TCGdex set id).
 *
 * Yu-Gi-Oh! (YGOPRODeck): many (not all) sets have real box-art, bulk-
 * fetched in a single `GET /cardsets.php` call, keyed by set_code.
 *
 * No other current game's source (Lorcast, Riftcodex, SWU-DB, DeckPlanet,
 * onepiece-cardgame mirror, or the cardora-sets-data sports checklists)
 * publishes any set-level image -- confirmed by inspecting each API
 * response directly, not assumed. Those games are left alone here.
 */
class EnrichSetImages extends Command
{
    protected $signature = 'cardora:enrich-set-images {game? : magic-the-gathering|pokemon|yugioh (default: all three)}';

    protected $description = 'Backfill binder_sets.image_url (set icon/logo/box-art) from each source that publishes one';

    private array $report = ['checked' => 0, 'filled' => 0, 'unavailable' => 0];

    public function handle(): int
    {
        $game = $this->argument('game');
        $targets = $game ? [$game] : ['magic-the-gathering', 'pokemon', 'yugioh'];

        foreach ($targets as $slug) {
            match ($slug) {
                'magic-the-gathering' => $this->enrichMagic(),
                'pokemon' => $this->enrichPokemon(),
                'yugioh' => $this->enrichYugioh(),
                default => $this->error("Unknown/unsupported game: {$slug}"),
            };
        }

        $this->info('--- Set image enrichment report ---');
        foreach ($this->report as $k => $v) {
            $this->line("{$k}: {$v}");
        }

        return self::SUCCESS;
    }

    private function enrichMagic(): void
    {
        $gameId = DB::table('binder_games')->where('slug', 'magic-the-gathering')->value('id');
        if (! $gameId) {
            return;
        }

        $this->info('Fetching Scryfall bulk sets list...');
        try {
            $resp = Http::withHeaders(['User-Agent' => 'CardoraBinderEnrichment/1.0', 'Accept' => '*/*'])
                ->timeout(30)->get('https://api.scryfall.com/sets');
        } catch (\Throwable $e) {
            $this->error('Scryfall /sets failed: '.$e->getMessage());

            return;
        }

        $iconByCode = [];
        foreach ($resp->json('data') ?? [] as $set) {
            if (! empty($set['code']) && ! empty($set['icon_svg_uri'])) {
                $iconByCode[strtolower($set['code'])] = $set['icon_svg_uri'];
            }
        }
        $this->info('Indexed '.count($iconByCode).' set icons.');

        $rows = DB::table('binder_sets')->where('game_id', $gameId)->whereNull('image_url')->get(['id', 'set_code']);
        foreach ($rows as $row) {
            $this->report['checked']++;
            $icon = $row->set_code ? ($iconByCode[strtolower($row->set_code)] ?? null) : null;
            if ($icon) {
                DB::table('binder_sets')->where('id', $row->id)->update(['image_url' => $icon, 'updated_at' => now()]);
                $this->report['filled']++;
            } else {
                $this->report['unavailable']++;
            }
        }
    }

    private function enrichPokemon(): void
    {
        $gameId = DB::table('binder_games')->where('slug', 'pokemon')->value('id');
        if (! $gameId) {
            return;
        }

        $rows = DB::table('binder_sets')->where('game_id', $gameId)->whereNull('image_url')->get(['id', 'source_set_id']);
        $this->info("Checking {$rows->count()} Pokemon sets against TCGdex (one request per set)...");

        foreach ($rows as $row) {
            $this->report['checked']++;
            if (! $row->source_set_id) {
                $this->report['unavailable']++;

                continue;
            }

            try {
                $resp = Http::timeout(15)->get("https://api.tcgdex.net/v2/en/sets/{$row->source_set_id}");
            } catch (\Throwable $e) {
                $this->report['unavailable']++;

                continue;
            }

            $logo = $resp->ok() ? $resp->json('logo') : null;
            if ($logo) {
                // Not every set logo has a .png rendition (confirmed: some
                // 404); .webp is the one extension that resolved for every
                // set checked, including older ones like "ecard1".
                DB::table('binder_sets')->where('id', $row->id)->update(['image_url' => $logo.'.webp', 'updated_at' => now()]);
                $this->report['filled']++;
            } else {
                $this->report['unavailable']++;
            }

            usleep(60_000);
        }
    }

    private function enrichYugioh(): void
    {
        $gameId = DB::table('binder_games')->where('slug', 'yugioh')->value('id');
        if (! $gameId) {
            return;
        }

        $this->info('Fetching YGOPRODeck bulk cardsets list...');
        try {
            $resp = Http::timeout(30)->get('https://db.ygoprodeck.com/api/v7/cardsets.php');
        } catch (\Throwable $e) {
            $this->error('YGOPRODeck /cardsets.php failed: '.$e->getMessage());

            return;
        }

        $imageByCode = [];
        foreach ($resp->json() ?? [] as $set) {
            if (! empty($set['set_code']) && ! empty($set['set_image'])) {
                $imageByCode[strtoupper($set['set_code'])] = $set['set_image'];
            }
        }
        $this->info('Indexed '.count($imageByCode).' set images.');

        $rows = DB::table('binder_sets')->where('game_id', $gameId)->whereNull('image_url')->get(['id', 'set_code']);
        foreach ($rows as $row) {
            $this->report['checked']++;
            $image = $row->set_code ? ($imageByCode[strtoupper($row->set_code)] ?? null) : null;
            if ($image) {
                DB::table('binder_sets')->where('id', $row->id)->update(['image_url' => $image, 'updated_at' => now()]);
                $this->report['filled']++;
            } else {
                $this->report['unavailable']++;
            }
        }
    }
}
