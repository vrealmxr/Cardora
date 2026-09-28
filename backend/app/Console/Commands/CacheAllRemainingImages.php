<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Bulk-downloads every remaining externally-hotlinked v2 card image
 * (Magic/Scryfall, Pokemon/TCGdex, Yu-Gi-Oh!/YGOPRODeck) purely to
 * populate a local copy for export/packaging (e.g. handing a complete
 * offline image set to an external training pipeline) -- these 3
 * sources are CORS-open and already serve fine live in production, so
 * unlike the earlier CORP-blocked/broken-path fixes this session, there
 * is no reason to repoint the live site at local copies. This command
 * deliberately does NOT touch binder_card_variants.image_small/large;
 * it only writes files under public/cards/.
 */
class CacheAllRemainingImages extends Command
{
    protected $signature = 'cardora:cache-all-remaining-images
        {--game= : Limit to one game slug (magic-the-gathering|pokemon|yugioh)}
        {--limit= : Cap rows processed, for a quick check}
        {--concurrency=30}';

    protected $description = 'Download and locally cache all remaining externally-hosted v2 card images';

    private array $report = ['checked' => 0, 'downloaded' => 0, 'deduped' => 0, 'failed' => 0];

    public function handle(): int
    {
        $games = $this->option('game') ? [$this->option('game')] : ['magic-the-gathering', 'pokemon', 'yugioh'];
        $concurrency = (int) $this->option('concurrency');

        foreach ($games as $slug) {
            $this->info("=== {$slug} ===");
            $this->cacheGame($slug, $concurrency);
        }

        $this->info('--- Report ---');
        foreach ($this->report as $k => $v) {
            $this->line("{$k}: {$v}");
        }

        return self::SUCCESS;
    }

    private function cacheGame(string $slug, int $concurrency): void
    {
        $gameId = DB::table('binder_games')->where('slug', $slug)->value('id');
        if (! $gameId) {
            $this->error("Unknown game: {$slug}");

            return;
        }

        $rows = DB::table('binder_card_variants as v')
            ->join('binder_cards as c', 'c.id', '=', 'v.card_id')
            ->join('binder_sets as s', 's.id', '=', 'c.set_id')
            ->where('c.game_id', $gameId)
            ->whereNotNull('c.card_key')
            ->where(function ($q) {
                $q->where('v.image_large', 'like', 'http%')->orWhere('v.image_small', 'like', 'http%');
            })
            ->select('v.id as variant_id', 'v.variant_key', 'v.image_small', 'v.image_large', 'c.card_key', 's.set_key')
            ->when($this->option('limit'), fn ($q) => $q->limit((int) $this->option('limit')))
            ->get();

        $this->info("{$rows->count()} variants to fetch.");

        foreach ($rows->chunk($concurrency) as $batch) {
            $responses = Http::pool(fn ($pool) => $batch->map(
                fn ($row) => $pool->as($row->variant_id)
                    ->withHeaders(['User-Agent' => 'CardoraBinderEnrichment/1.0', 'Accept' => '*/*'])
                    ->timeout(15)->get($row->image_large ?: $row->image_small)
            )->all());

            foreach ($batch as $row) {
                $this->report['checked']++;
                $response = $responses[$row->variant_id] ?? null;
                $sourceUrl = $row->image_large ?: $row->image_small;

                // Downloads to public/cards/ for export packaging only --
                // deliberately not written back to the DB, see class docblock.
                $this->storeImage($response, $sourceUrl, $slug, $row->set_key, $row->card_key, $row->variant_key);
            }

            if ($this->report['checked'] % 1000 < $concurrency) {
                $this->info("... {$this->report['checked']}/{$rows->count()}");
            }
        }
    }

    private function storeImage($response, string $url, string $game, string $setKey, string $cardKey, string $variantKey): ?string
    {
        if (! $response || $response instanceof \Throwable || ! $response->ok()) {
            $this->report['failed']++;

            return null;
        }

        $bytes = $response->body();
        if (! $bytes) {
            $this->report['failed']++;

            return null;
        }

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $ext = strtolower(explode('?', $ext)[0]) ?: 'jpg';
        $hash = hash('sha256', $bytes);
        $canonicalPath = public_path("cards/_by-hash/{$hash}.{$ext}");
        File::ensureDirectoryExists(dirname($canonicalPath));

        if (! File::exists($canonicalPath)) {
            File::put($canonicalPath, $bytes);
            $this->report['downloaded']++;
        } else {
            $this->report['deduped']++;
        }

        $publicDir = public_path("cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}");
        File::ensureDirectoryExists($publicDir);
        $destPath = "{$publicDir}/front.{$ext}";
        if (! File::exists($destPath)) {
            File::copy($canonicalPath, $destPath);
        }

        return "/cards/{$game}/{$setKey}/{$cardKey}/{$variantKey}/front.{$ext}";
    }
}
