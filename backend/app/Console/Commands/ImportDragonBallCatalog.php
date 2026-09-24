<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dragon Ball Super Card Game has two genuinely different rulesets sharing
 * one cardora-sets-data folder (Anime/Dragonball): "Masters" (the original
 * 2017-2023 game, BT/DBS-B-coded boosters, continuing as BT21+/DBS-B24..32
 * despite the later date range) and "Fusion World" (the 2024+ relaunch,
 * its own FB/FS/SB-coded product line). ImportBinderCatalog's one-folder-
 * one-game model can't express that split, so this extends it and reuses
 * its card-file parsing (importCardFile/parseDate, and TcgplayerExtendedDataParser
 * for extendedData -- NOT reimplemented here, so the two commands can't
 * diverge on extended-field parsing) while partitioning sets.csv by
 * abbreviation prefix before writing to two separate binder_games.
 */
class ImportDragonBallCatalog extends ImportBinderCatalog
{
    protected $signature = 'cardora:binder-import-dragonball
        {--path= : Path to the cardora-sets-data/Anime/Dragonball folder}';

    protected $description = 'Split-import Dragon Ball Super Card Game (Masters vs Fusion World) into two binder_games';

    private const MASTERS_PREFIXES = ['BT', 'DBS-B', 'DBS-EB', 'DBS-TB', 'DBS_BSB', 'BE25'];
    private const MASTERS_EXACT = ['PR', 'JPR', 'TPR', 'MB-01', 'OVRS', 'MSP', 'CSV1', 'CSV2', 'CSV3', 'TS01', 'TS02', 'EB-01', 'DB0', 'DB1', 'DB2', 'DB3', 'RP20'];
    private const FUSION_PREFIXES = ['FB', 'FS', 'SB'];

    private const LINES = [
        'masters' => ['dragon-ball-super-masters', 'Dragon Ball Super Card Game (Masters)', 90],
        'fusion' => ['dragon-ball-super-fusion-world', 'Dragon Ball Super Card Game: Fusion World', 91],
    ];

    private const INSERT_CHUNK_SIZE = 500;

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('../../cardora-sets-data/Anime/Dragonball');
        if (! is_dir($path)) {
            $this->error("Folder not found: {$path}");

            return self::FAILURE;
        }

        $setsCsvPath = $path.'/sets.csv';
        $cardsDir = $path.'/Cards';

        [$mastersSets, $fusionSets, $unclassified] = $this->classifySets($setsCsvPath);

        if ($unclassified !== []) {
            $this->error('Unclassified set(s) found -- refusing to guess, add them to MASTERS/FUSION lists explicitly:');
            foreach ($unclassified as [$abbr, $name]) {
                $this->line("  {$abbr}\t{$name}");
            }

            return self::FAILURE;
        }

        $this->info('== Dragon Ball Super Card Game (Masters) == ('.count($mastersSets).' sets)');
        $this->importLine('masters', $mastersSets, $cardsDir);

        $this->info('== Dragon Ball Super Card Game: Fusion World == ('.count($fusionSets).' sets)');
        $this->importLine('fusion', $fusionSets, $cardsDir);

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    /** @return array{0: array, 1: array, 2: array} [masters rows, fusion rows, unclassified [abbr,name] pairs] */
    private function classifySets(string $setsCsvPath): array
    {
        $handle = fopen($setsCsvPath, 'r');
        $header = fgetcsv($handle);
        $masters = [];
        $fusion = [];
        $unclassified = [];

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);
            if (! $record || $record['groupId'] === '') {
                continue;
            }
            $abbr = $record['abbreviation'];

            if (in_array($abbr, self::MASTERS_EXACT, true) || $this->startsWithAny($abbr, self::MASTERS_PREFIXES)) {
                $masters[] = $record;
            } elseif ($this->startsWithAny($abbr, self::FUSION_PREFIXES)) {
                $fusion[] = $record;
            } else {
                $unclassified[] = [$abbr, $record['name']];
            }
        }
        fclose($handle);

        return [$masters, $fusion, $unclassified];
    }

    private function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function importLine(string $lineKey, array $setRecords, string $cardsDir): void
    {
        [$slug, $name, $sortOrder] = self::LINES[$lineKey];

        /** @var BinderGame $game */
        $game = BinderGame::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'category' => 'tcg', 'catalog_group' => 'anime', 'sort_order' => $sortOrder],
        );

        $now = now();
        $rows = [];
        foreach ($setRecords as $record) {
            $groupId = (int) $record['groupId'];
            $recordName = trim((string) $record['name']);
            $rows[$groupId] = [
                'game_id' => $game->id,
                'external_group_id' => $groupId,
                'slug' => Str::slug($recordName.'-'.$groupId),
                'name' => $recordName !== '' ? $recordName : "Set {$groupId}",
                'abbreviation' => $record['abbreviation'] !== '' ? $record['abbreviation'] : null,
                'released_at' => $this->parseDate($record['publishedOn'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK_SIZE, true) as $chunk) {
            DB::table('binder_sets')->upsert(
                array_values($chunk),
                ['game_id', 'external_group_id'],
                ['slug', 'name', 'abbreviation', 'released_at', 'updated_at'],
            );
        }

        $setIdByGroupId = DB::table('binder_sets')->where('game_id', $game->id)
            ->pluck('id', 'external_group_id')->map(fn ($id) => (int) $id)->all();

        $totalCards = 0;
        foreach (glob($cardsDir.'/*.csv') ?: [] as $file) {
            // Only files belonging to a set in THIS line -- a filename that doesn't
            // match any set name of this line is simply skipped (belongs to the
            // other line), never guessed at.
            $baseName = pathinfo($file, PATHINFO_FILENAME);
            $matchesThisLine = false;
            foreach ($setRecords as $record) {
                if (Str::of($record['name'])->replace(['/', ':'], '')->trim()->is($baseName)
                    || trim($record['name']) === $baseName) {
                    $matchesThisLine = true;
                    break;
                }
            }
            if (! $matchesThisLine) {
                continue;
            }
            $totalCards += $this->importCardFile($game, $setIdByGroupId, $file);
        }
        $this->line("  Cards/: {$totalCards} cards");

        // catalog_status reflects whether real card-level data actually landed for
        // THIS line, not just whether the line's set names are verified -- Fusion
        // World's one set (FB01) is real, but no Cards/*.csv exists for it at all.
        $game->update(['catalog_status' => $totalCards > 0 ? 'source_ready' : 'source_needed']);

        $this->refreshSetCardCounts($game);
    }
}
