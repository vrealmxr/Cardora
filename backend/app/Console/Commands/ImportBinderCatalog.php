<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
use App\Support\TcgplayerExtendedDataParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportBinderCatalog extends Command
{
    protected $signature = 'cardora:binder-import
        {--path= : Path to the cardora-sets-data checkout (defaults to ../cardora-sets-data next to the repo)}
        {--game= : Only import this game slug (e.g. pokemon)}';

    protected $description = 'Import TCG set/card catalogs from the cardora-sets-data CSV export into binder_* tables';

    /**
     * game slug => [display name, category, sort_order, folder path relative to the data root]
     *
     * Registers binder_games + binder_sets for every game we've identified,
     * including ones with no real card-level data yet -- importCardFile()
     * already refuses to import a card row from an "ERROR, No URL found"
     * placeholder CSV (no `productId` column, so it returns 0 immediately),
     * so re-running this for a source_needed game safely yields sets with
     * card_count=0, never a fabricated "ERROR" card. catalog_group/
     * catalog_status aren't set here -- see the one-off governance pass
     * that renamed disney/star-wars and classified every row (2026-09-25).
     *
     * Marvel and DC Comics are deliberately NOT listed here: their
     * sets.csv rows are comic-book issue lists (10823 / 8979 rows), not
     * booster sets -- whether/how those map to binder_sets is a separate
     * modeling decision, not made by just running this importer.
     *
     * Dragon Ball is deliberately NOT listed here either -- its single
     * folder mixes two different rulesets (Masters vs Fusion World) that
     * need a prefix-based split, not a straight one-folder-one-game import
     * (see ImportDragonBallCatalog).
     */
    private const GAMES = [
        'pokemon' => ['Pokémon', 'tcg', 10, 'TCG/Pokemon'],
        'yugioh' => ['Yu-Gi-Oh!', 'tcg', 20, 'TCG/Yugioh'],
        'magic-the-gathering' => ['Magic: The Gathering', 'tcg', 30, 'TCG/Magic The gathering'],
        'one-piece' => ['One Piece', 'tcg', 40, 'TCG/One Piece'],
        'disney-lorcana' => ['Disney Lorcana', 'tcg', 50, 'TCG/Disney'],
        'star-wars-miniatures' => ['Star Wars Miniatures', 'collectible', 60, 'Entertainment/Star Wars'],
        'riftbound' => ['Riftbound', 'tcg', 11, 'Gaming/League of Legends'],
        'nba' => ['NBA', 'sports', 10, 'Sports/NBA'],
        'nfl' => ['NFL', 'sports', 20, 'Sports/NFL'],
        'mlb' => ['MLB', 'sports', 30, 'Sports/MLB'],
        'ufc' => ['UFC', 'sports', 40, 'Sports/UFC'],
        'soccer' => ['Soccer', 'sports', 50, 'Sports/Soccer'],
        'euroleague' => ['Euroleague', 'sports', 60, 'Sports/Euroleague'],
        'formula-1' => ['Formula 1', 'sports', 70, 'Sports/Formula 1'],
        'fortnite' => ['Fortnite', 'gaming', 20, 'Gaming/Fortnite'],
        'minecraft' => ['Minecraft', 'gaming', 30, 'Gaming/Minecraft'],
        'world-of-warcraft' => ['World of Warcraft', 'gaming', 40, 'Gaming/World of Warcraft'],
        'overwatch' => ['Overwatch', 'gaming', 50, 'Gaming/Overwatch'],
        'naruto' => ['Naruto', 'tcg', 80, 'Anime/Naruto'],
        'bleach' => ['Bleach', 'tcg', 90, 'Anime/Bleach'],
        'demon-slayer' => ['Demon Slayer', 'tcg', 100, 'Anime/Demon Slayer'],
        'jujutsu-kaisen' => ['Jujutsu Kaisen', 'tcg', 110, 'Anime/Jujutsu Kaisen'],
        'attack-on-titan' => ['Attack on Titan', 'tcg', 120, 'Anime/Attack on Titan'],
        'harry-potter' => ['Harry Potter', 'tcg', 130, 'Entertainment/Harry Potter'],
        'lord-of-the-rings' => ['The Lord of the Rings', 'tcg', 140, 'Entertainment/The Lord Of The Rings'],
    ];

    private const INSERT_CHUNK_SIZE = 500;

    public function handle(): int
    {
        $dataRoot = $this->resolveDataRoot();

        if ($dataRoot === null) {
            $this->error('Could not find the cardora-sets-data checkout. Pass --path explicitly.');

            return self::FAILURE;
        }

        $onlySlug = $this->option('game');
        $games = $onlySlug ? array_intersect_key(self::GAMES, [$onlySlug => true]) : self::GAMES;

        if ($games === []) {
            $this->error("Unknown game slug: {$onlySlug}");

            return self::FAILURE;
        }

        foreach ($games as $slug => [$name, $category, $sortOrder, $relativePath]) {
            $this->importGame($slug, $name, $category, $sortOrder, $dataRoot . '/' . $relativePath);
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function resolveDataRoot(): ?string
    {
        $configured = $this->option('path');

        if ($configured && is_dir($configured)) {
            return rtrim($configured, '/');
        }

        $candidates = [
            base_path('../cardora-sets-data'),
            base_path('../../cardora-sets-data'),
            '/Users/vrealm/Documents/GitHub/cardora-sets-data',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return rtrim($candidate, '/');
            }
        }

        return null;
    }

    private function importGame(string $slug, string $name, string $category, int $sortOrder, string $gamePath): void
    {
        if (! is_dir($gamePath)) {
            $this->warn("Skipping {$name}: folder not found at {$gamePath}");

            return;
        }

        $this->info("== {$name} ==");

        /** @var BinderGame $game */
        $game = BinderGame::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'category' => $category, 'sort_order' => $sortOrder],
        );

        $setsCsvPath = $gamePath . '/sets.csv';

        if (! is_file($setsCsvPath)) {
            $this->warn("  no sets.csv found, skipping");

            return;
        }

        // external_group_id => binder_sets.id
        $setIdByGroupId = $this->importSets($game, $setsCsvPath);

        $this->importCards($game, $setIdByGroupId, $gamePath . '/Cards');

        $this->refreshSetCardCounts($game);
    }

    /**
     * @return array<int, int> external_group_id => binder_sets.id
     */
    /**
     * `groupId` is a genuine, stable, unique-per-set TCGplayer numeric id
     * for the original catalog-v1 games (Pokemon/Yugioh/Magic/...). For
     * several newly-registered games (NBA/Euroleague/Fortnite/Naruto/
     * Harry Potter/...) it's instead a manufacturer+year *label* string
     * (e.g. "NBA-2020-Panin") that (a) isn't numeric -- (int) casts every
     * row to 0, silently collapsing 30 sets into 1 via the upsert key --
     * and (b) isn't even unique per real set within one game (two
     * different 2020-21 NBA products share that exact label). Anything
     * that doesn't parse as a clean positive integer gets a synthetic
     * external_group_id instead (a high base so it can never collide with
     * a real TCGplayer id), with the original string preserved verbatim
     * in source_set_id for traceability -- never silently dropped or
     * treated as if it were a real numeric identity.
     */
    private const SYNTHETIC_GROUP_ID_BASE = 900_000_000;

    private function importSets(BinderGame $game, string $setsCsvPath): array
    {
        $handle = fopen($setsCsvPath, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        $now = now();
        $syntheticCounter = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);

            if (! $record || $record['groupId'] === '') {
                continue;
            }

            $rawGroupId = $record['groupId'];
            $isRealNumericId = ctype_digit($rawGroupId);
            $groupId = $isRealNumericId ? (int) $rawGroupId : self::SYNTHETIC_GROUP_ID_BASE + $syntheticCounter++;
            $name = trim((string) $record['name']);

            $rows[$groupId] = [
                'game_id' => $game->id,
                'external_group_id' => $groupId,
                'slug' => Str::slug($name . '-' . $groupId),
                'name' => $name !== '' ? $name : "Set {$groupId}",
                'abbreviation' => $record['abbreviation'] !== '' ? $record['abbreviation'] : null,
                'released_at' => $this->parseDate($record['publishedOn'] ?? null),
                'source' => $isRealNumericId ? null : 'cardora-sets-data-csv',
                'source_set_id' => $isRealNumericId ? null : $rawGroupId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        $this->line('  sets.csv: ' . count($rows) . ' sets');

        foreach (array_chunk($rows, self::INSERT_CHUNK_SIZE, true) as $chunk) {
            DB::table('binder_sets')->upsert(
                array_values($chunk),
                ['game_id', 'external_group_id'],
                ['slug', 'name', 'abbreviation', 'released_at', 'source', 'source_set_id', 'updated_at'],
            );
        }

        return DB::table('binder_sets')
            ->where('game_id', $game->id)
            ->pluck('id', 'external_group_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param array<int, int> $setIdByGroupId
     */
    private function importCards(BinderGame $game, array $setIdByGroupId, string $cardsDir): void
    {
        if (! is_dir($cardsDir)) {
            $this->warn('  no Cards/ folder found');

            return;
        }

        $files = glob($cardsDir . '/*.csv') ?: [];
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        $totalCards = 0;

        foreach ($files as $file) {
            $totalCards += $this->importCardFile($game, $setIdByGroupId, $file);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  Cards/: {$totalCards} cards across " . count($files) . ' files');
    }

    protected function importCardFile(BinderGame $game, array $setIdByGroupId, string $file): int
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header === false || ! in_array('productId', $header, true)) {
            fclose($handle);

            return 0;
        }

        $now = now();
        $buffer = [];
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);

            if (! $record || $record['productId'] === '' || $record['groupId'] === '') {
                continue;
            }

            $groupId = (int) $record['groupId'];
            $setId = $setIdByGroupId[$groupId] ?? null;

            if ($setId === null) {
                continue;
            }

            $extended = (string) ($record['extendedData'] ?? '');

            $buffer[] = [
                'set_id' => $setId,
                'game_id' => $game->id,
                'external_product_id' => (int) $record['productId'],
                'name' => trim((string) $record['name']),
                'clean_name' => $record['cleanName'] !== '' ? $record['cleanName'] : null,
                'number' => TcgplayerExtendedDataParser::field($extended, 'Number'),
                'rarity' => TcgplayerExtendedDataParser::field($extended, 'Rarity'),
                'card_type' => TcgplayerExtendedDataParser::field($extended, 'Card Type'),
                'image_url' => $record['imageUrl'] !== '' ? $record['imageUrl'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $count++;

            if (count($buffer) >= self::INSERT_CHUNK_SIZE) {
                $this->flushCards($buffer);
                $buffer = [];
            }
        }

        fclose($handle);

        if ($buffer !== []) {
            $this->flushCards($buffer);
        }

        return $count;
    }

    protected function flushCards(array $rows): void
    {
        DB::table('binder_cards')->upsert(
            $rows,
            ['external_product_id'],
            ['set_id', 'game_id', 'name', 'clean_name', 'number', 'rarity', 'card_type', 'image_url', 'updated_at'],
        );
    }

    protected function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function refreshSetCardCounts(BinderGame $game): void
    {
        // Only count actual cards (rows with a Number), not sealed products
        // like booster boxes — matches what the set list badge should show.
        DB::statement(
            'UPDATE binder_sets s
             SET card_count = (SELECT COUNT(*) FROM binder_cards c WHERE c.set_id = s.id AND c.number IS NOT NULL)
             WHERE s.game_id = ?',
            [$game->id],
        );
    }
}
