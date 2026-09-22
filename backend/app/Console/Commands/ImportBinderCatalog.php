<?php

namespace App\Console\Commands;

use App\Models\BinderGame;
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
     * Only games with a real (non-error-placeholder) catalog in the data
     * export are listed here. Sports (Basketball/Soccer/Football/F1/UFC/MLB)
     * and Fortnite/Minecraft/World of Warcraft only have scrape-failure
     * placeholder CSVs right now ("ERROR, No URL found") — add them here
     * once cardora-sets-data actually has real rows for them.
     */
    private const GAMES = [
        'pokemon' => ['Pokémon', 'tcg', 10, 'TCG/Pokemon'],
        'yugioh' => ['Yu-Gi-Oh!', 'tcg', 20, 'TCG/Yugioh'],
        'magic-the-gathering' => ['Magic: The Gathering', 'tcg', 30, 'TCG/Magic The gathering'],
        'one-piece' => ['One Piece', 'tcg', 40, 'TCG/One Piece'],
        'disney' => ['Disney', 'tcg', 50, 'TCG/Disney'],
        'star-wars' => ['Star Wars', 'tcg', 60, 'Entertainment/Star Wars'],
        'riftbound' => ['League of Legends', 'gaming', 10, 'Gaming/League of Legends'],
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
    private function importSets(BinderGame $game, string $setsCsvPath): array
    {
        $handle = fopen($setsCsvPath, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);

            if (! $record || $record['groupId'] === '') {
                continue;
            }

            $groupId = (int) $record['groupId'];
            $name = trim((string) $record['name']);

            $rows[$groupId] = [
                'game_id' => $game->id,
                'external_group_id' => $groupId,
                'slug' => Str::slug($name . '-' . $groupId),
                'name' => $name !== '' ? $name : "Set {$groupId}",
                'abbreviation' => $record['abbreviation'] !== '' ? $record['abbreviation'] : null,
                'released_at' => $this->parseDate($record['publishedOn'] ?? null),
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
                ['slug', 'name', 'abbreviation', 'released_at', 'updated_at'],
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

    private function importCardFile(BinderGame $game, array $setIdByGroupId, string $file): int
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
                'number' => $this->extractExtendedField($extended, 'Number'),
                'rarity' => $this->extractExtendedField($extended, 'Rarity'),
                'card_type' => $this->extractExtendedField($extended, 'Card Type'),
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

    private function flushCards(array $rows): void
    {
        DB::table('binder_cards')->upsert(
            $rows,
            ['external_product_id'],
            ['set_id', 'game_id', 'name', 'clean_name', 'number', 'rarity', 'card_type', 'image_url', 'updated_at'],
        );
    }

    /**
     * The extendedData column is a Python repr() of a list of dicts, not
     * valid JSON (single quotes, True/False/None) — full parsing risks
     * breaking on embedded apostrophes in long text fields like CardText.
     * We only need a couple of short, simple fields, so a targeted regex
     * against the stable 'name' key is far more robust than a real parser.
     */
    private function extractExtendedField(string $extendedData, string $fieldName): ?string
    {
        $pattern = "/\\{'name':\\s*'" . preg_quote($fieldName, '/') . "',.*?'value':\\s*'([^']*)'/";

        if (preg_match($pattern, $extendedData, $matches) === 1) {
            // These are meant to be short flavor fields (a rarity name, a
            // type word, a card number). Truncate defensively rather than
            // trust the regex never over-matches into a much longer field —
            // safer than widening the column for what should always be a
            // few characters.
            $value = trim(mb_substr($matches[1], 0, 190));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function parseDate(?string $value): ?string
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

    private function refreshSetCardCounts(BinderGame $game): void
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
