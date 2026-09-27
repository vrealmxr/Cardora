<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * One Piece v2 variants store their image as an ABSOLUTE hotlink straight
 * to en.onepiece-cardgame.com -- unlike every other v2 game, which either
 * uses a CORS-open CDN (Scryfall, TCGdex, YGOPRODeck: all send
 * access-control-allow-origin: *) or is already cached locally (Lorcana,
 * Riftbound, Fusion World, Star Wars: Unlimited).
 *
 * onepiece-cardgame.com's response carries
 * `Cross-Origin-Resource-Policy: same-site`, which browsers enforce even
 * for a plain <img> tag -- confirmed via curl (200, real image bytes) vs.
 * what the browser actually renders (blocked, broken-image icon). This
 * has nothing to do with request headers or referrers; it's the source
 * server's own response policy, so hotlinking can never work here no
 * matter what the frontend does.
 *
 * Fix: download once and cache locally under public/cards/one-piece/...,
 * same SHA256-dedup convention as the other locally-cached games, then
 * rewrite image_small/image_large to the resulting relative path (which
 * BinderController's absolutizeImageUrl() already turns into a working
 * api.cardora.gr URL for the frontend).
 */
class CacheOnePieceImages extends Command
{
    protected $signature = 'cardora:cache-onepiece-images {--limit= : Cap how many variants to process, for a quick check} {--concurrency=15}';

    protected $description = 'Download and locally cache One Piece v2 card images (hotlinked source blocks cross-origin embedding)';

    private array $report = ['checked' => 0, 'downloaded' => 0, 'deduped' => 0, 'failed' => 0];

    public function handle(): int
    {
        $rows = DB::table('binder_card_variants as v')
            ->join('binder_cards as c', 'c.id', '=', 'v.card_id')
            ->join('binder_sets as s', 's.id', '=', 'c.set_id')
            ->where('c.game_id', DB::table('binder_games')->where('slug', 'one-piece')->value('id'))
            ->where('v.image_small', 'like', 'https://en.onepiece-cardgame.com%')
            ->select('v.id as variant_id', 'v.variant_key', 'v.image_small', 'c.card_key', 's.set_key')
            ->when($this->option('limit'), fn ($q) => $q->limit((int) $this->option('limit')))
            ->get();

        $this->info("Found {$rows->count()} One Piece variants hotlinking the source site.");

        $concurrency = (int) $this->option('concurrency');

        // The source site got noticeably slower under sustained sequential
        // requests (likely rate-limiting or just plain latency) -- a first
        // run stalled at ~1.7s/request. Fetching in small concurrent
        // batches via Http::pool() cuts wall-clock time a lot without
        // hammering the source any harder per-window than a slow
        // sequential loop already was.
        foreach ($rows->chunk($concurrency) as $batch) {
            $responses = Http::pool(fn ($pool) => $batch->map(
                fn ($row) => $pool->as($row->variant_id)->timeout(15)->get($row->image_small)
            )->all());

            foreach ($batch as $row) {
                $this->report['checked']++;
                $response = $responses[$row->variant_id] ?? null;

                $newPath = $this->storeImage($response, $row->image_small, 'one-piece', $row->set_key, $row->card_key, $row->variant_key);
                if ($newPath) {
                    DB::table('binder_card_variants')->where('id', $row->variant_id)->update([
                        'image_small' => $newPath,
                        'image_large' => $newPath,
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($this->report['checked'] % 200 < $concurrency) {
                $this->info("... {$this->report['checked']}/{$rows->count()}");
            }
        }

        $this->info('--- One Piece image cache report ---');
        foreach ($this->report as $k => $v) {
            $this->line("{$k}: {$v}");
        }

        return self::SUCCESS;
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

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
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
