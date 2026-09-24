<?php

namespace App\Console\Commands;

use App\Services\Gameplay\GameplayResolver;
use App\Services\Gameplay\OnePieceGameplayNormalizer;
use App\Services\Gameplay\Policies\MagicGameplayPolicy;
use App\Services\Gameplay\Policies\OnePieceGameplayPolicy;
use App\Services\Gameplay\Policies\PokemonGameplayPolicy;
use App\Services\Gameplay\Policies\YugiohGameplayPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enriches existing binder_cards rows with resolved gameplay metadata
 * (binder_cards.gameplay_data), sourced from an independently-frozen
 * "gameplay snapshot" per game (see storage/app/gameplay-snapshots/ and
 * each snapshot's manifest.json) -- NOT the same frozen archive the
 * canonical catalog import used, and not required to be: this command
 * only ever UPDATEs an existing binder_cards row's gameplay_data column.
 *
 * Hard guards, enforced regardless of --apply:
 *   - never INSERTs into binder_cards or binder_card_variants
 *   - never DELETEs anything
 *   - never mutates canonical identity (card_key, set_key, game_id, ...)
 *   - --apply refuses to run at all if the dry-run it computes first has
 *     any ambiguous_matches or unresolved_gameplay_conflicts > 0
 *
 * Default is --dry-run (no writes at all, just the report). --apply
 * still computes and prints the same report first; only if it's clean
 * does it proceed to the UPDATE pass.
 */
class GameplayBackfill extends Command
{
    protected $signature = 'cardora:gameplay-backfill
        {--game=all : magic|pokemon|yugioh|onepiece|all}
        {--dry-run : Compute and print the report only (default behavior)}
        {--apply : Actually UPDATE binder_cards.gameplay_data -- refused if the dry-run isn\'t clean}
        {--snapshot= : Base directory containing gameplay-snapshots/<game>/manifest.json + raw file(s); default storage/app/gameplay-snapshots}';

    protected $description = 'Enrich binder_cards.gameplay_data from an independently-frozen gameplay snapshot (identity/variants untouched).';

    private const GAME_SLUGS = [
        'magic' => 'magic-the-gathering',
        'pokemon' => 'pokemon',
        'yugioh' => 'yugioh',
        'onepiece' => 'one-piece',
    ];

    public function handle(): int
    {
        $requested = $this->option('game');
        $games = $requested === 'all' ? array_keys(self::GAME_SLUGS) : [$requested];
        foreach ($games as $g) {
            if (! isset(self::GAME_SLUGS[$g])) {
                $this->error("Unknown --game={$g}. Expected one of: ".implode('|', array_keys(self::GAME_SLUGS)).'|all');

                return self::FAILURE;
            }
        }

        $snapshotBase = $this->option('snapshot') ?: storage_path('app/gameplay-snapshots');
        $apply = (bool) $this->option('apply');
        $resolvedAt = now()->toIso8601String();

        // Pass 1: gate-check every requested game. Reports only -- envelopes are discarded
        // immediately (unset + gc) so running --game=all never holds more than one game's
        // full result set in memory at once (each game's raw snapshot + envelope map can be
        // tens of MB; holding all 4 simultaneously is what OOM'd under a normal shared-hosting
        // memory_limit during development of this command).
        $fullReport = [];
        $anyGateFailed = false;
        foreach ($games as $game) {
            $this->info("== {$game} ==");
            $result = $this->resolveGame($game, $snapshotBase, $resolvedAt);
            if ($result === null) {
                $anyGateFailed = true;

                continue;
            }
            $fullReport[$game] = $result['report'];
            $this->line(json_encode($result['report'], JSON_PRETTY_PRINT));
            if ($this->gateFailed($result['report'])) {
                $anyGateFailed = true;
                $this->error("  HARD-FAIL gate tripped for {$game} -- --apply will refuse this game.");
            }
            unset($result);
            gc_collect_cycles();
        }

        if (! $apply) {
            $this->info('Dry-run only (default). Pass --apply to write, once every game above is clean.');

            return $anyGateFailed ? self::FAILURE : self::SUCCESS;
        }

        if ($anyGateFailed) {
            $this->error('--apply refused: at least one game failed its hard-FAIL gate above. No writes made.');

            return self::FAILURE;
        }

        // Pass 2: every game passed its gate -- resolve and apply one game at a time
        // (recomputing rather than reusing pass 1's envelopes, the same memory tradeoff).
        foreach ($games as $game) {
            $result = $this->resolveGame($game, $snapshotBase, $resolvedAt);
            $envelopes = $result['envelopes'];
            $this->info("Applying {$game}: ".count($envelopes).' rows...');
            DB::transaction(function () use ($envelopes) {
                foreach ($envelopes as $cardKey => $envelope) {
                    // UPDATE only -- card_key is the WHERE clause and is never written to, so this can never
                    // create a row (0 rows affected if the key doesn't exist, not an upsert).
                    DB::table('binder_cards')->where('card_key', $cardKey)->update([
                        'gameplay_data' => $envelope->toJson(),
                    ]);
                }
            });
            unset($result, $envelopes);
            gc_collect_cycles();
        }
        $this->info('Apply complete.');

        return self::SUCCESS;
    }

    /**
     * `resolved_at` is intentionally "now" on every run, so a raw JSON-string
     * compare against a stored envelope would report "changed" on every
     * single rerun forever, even when nothing meaningful did. Idempotency
     * means the same MEANINGFUL content (schema_version/source/source_version/
     * provenance/data) resolves the same way twice in a row -- resolved_at
     * excluded from the comparison on both sides.
     */
    private function contentChanged(string $existingJson, \App\Services\Gameplay\GameplayEnvelope $envelope): bool
    {
        $existing = json_decode($existingJson, true);
        if (! is_array($existing)) {
            return true; // malformed stored value -- treat as a real change
        }
        unset($existing['resolved_at']);
        $new = $envelope->toArray();
        unset($new['resolved_at']);

        return json_encode($existing) !== json_encode($new);
    }

    private function gateFailed(array $report): bool
    {
        return $report['ambiguous_matches'] > 0
            || $report['unresolved_gameplay_conflicts'] > 0
            || $report['cards_that_would_be_created'] > 0
            || $report['variants_that_would_be_created'] > 0
            || $report['canonical_identity_changes'] > 0;
    }

    /** @return array{report: array, envelopes: array}|null null if the snapshot manifest is missing */
    private function resolveGame(string $game, string $snapshotBase, string $resolvedAt): ?array
    {
        $snapshotDir = "{$snapshotBase}/{$game}";
        $manifestPath = "{$snapshotDir}/manifest.json";
        if (! is_file($manifestPath)) {
            $this->error("  manifest.json not found at {$manifestPath} -- skipping {$game}");

            return null;
        }
        $manifest = json_decode(file_get_contents($manifestPath), true);

        [$policy, $rawPath] = $this->buildPolicy($game, $manifest, $snapshotDir);

        $gameSlug = self::GAME_SLUGS[$game];
        $gameRow = DB::table('binder_games')->where('slug', $gameSlug)->first();
        $canonicalCards = DB::table('binder_cards')
            ->where('game_id', $gameRow->id)->whereNotNull('card_key')
            ->select('id', 'card_key', 'card_type', 'gameplay_data')
            ->get()->map(fn ($r) => (array) $r)->all();

        $externalIdRows = $game === 'yugioh'
            ? DB::table('binder_card_external_ids')->where('entity_type', 'card')
                ->whereIn('entity_key', array_column($canonicalCards, 'card_key'))
                ->select('entity_key', 'provider', 'external_type', 'external_id')
                ->get()->map(fn ($r) => (array) $r)->all()
            : [];

        $variantRows = $game === 'onepiece'
            ? DB::table('binder_card_variants')
                ->join('binder_cards', 'binder_cards.id', '=', 'binder_card_variants.card_id')
                ->whereNotNull('binder_card_variants.source_variant_id')
                ->select('binder_card_variants.source_variant_id', 'binder_cards.card_key')
                ->get()->map(fn ($r) => (array) $r)->all()
            : [];

        $resolver = new GameplayResolver($policy);
        $result = $resolver->run($rawPath, $canonicalCards, $externalIdRows, $variantRows, $resolvedAt);

        $existingByKey = array_column($canonicalCards, 'gameplay_data', 'card_key');
        $alreadyPresent = 0;
        $wouldChange = 0;
        $wouldBeAdded = 0;
        foreach ($result['envelopes'] as $cardKey => $envelope) {
            $existing = $existingByKey[$cardKey] ?? null;
            if ($existing === null) {
                $wouldBeAdded++;
            } elseif ($this->contentChanged($existing, $envelope)) {
                $wouldChange++;
            } else {
                $alreadyPresent++;
            }
        }
        $result['report']['gameplay_data_already_present'] = $alreadyPresent;
        $result['report']['gameplay_data_would_change'] = $wouldChange;
        $result['report']['gameplay_data_would_be_added'] = $wouldBeAdded;

        return $result;
    }

    /** @return array{0: \App\Services\Gameplay\Contracts\GameplayPolicy, 1: string} */
    private function buildPolicy(string $game, array $manifest, string $snapshotDir): array
    {
        return match ($game) {
            'magic' => [
                new MagicGameplayPolicy($manifest['source_version']),
                "{$snapshotDir}/".basename($manifest['jsonl_download_uri'] ?? 'default-cards-20260922210550.jsonl.gz'),
            ],
            'pokemon' => [
                new PokemonGameplayPolicy($manifest['source_version']),
                "{$snapshotDir}/cards.json",
            ],
            'yugioh' => [
                new YugiohGameplayPolicy($manifest['source_version']),
                "{$snapshotDir}/ygoprodeck-cardinfo-raw.json",
            ],
            'onepiece' => [
                new OnePieceGameplayPolicy(
                    $manifest['source_version'],
                    OnePieceGameplayNormalizer::loadOverrideRows(base_path('database/data/onepiece-supplemental/supplemental_gameplay_overrides.csv')),
                ),
                $snapshotDir, // policy globs {$snapshotDir}/english/cards/*/*.json itself
            ],
        };
    }
}
