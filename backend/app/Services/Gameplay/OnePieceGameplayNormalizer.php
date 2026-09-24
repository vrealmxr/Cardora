<?php

namespace App\Services\Gameplay;

/**
 * The One Piece TCG gameplay-consistency logic, extracted from
 * ImportOnePiece so the canonical importer and GameplayBackfill (the
 * gameplay_data enrichment command) share exactly one implementation of:
 * severe-vs-text-only field classification, types normalization (a
 * source scraping bug can leak Japanese characters into an English
 * field -- cosmetic, not severe), text normalization (fullwidth/minus
 * character variants), and supplemental_gameplay_overrides.csv
 * application. Diverging copies of this logic between the two commands
 * is exactly the failure mode this class exists to prevent.
 *
 * Stateless / no Command coupling: every method takes its inputs and
 * returns its outputs, so it works identically whether called from a
 * Command that also writes CSVs (ImportOnePiece) or one that only reads
 * the DB and reports (GameplayBackfill).
 */
class OnePieceGameplayNormalizer
{
    /**
     * Fields whose divergence across occurrences of the same base number
     * is a serious signal (see ImportOnePiece's EB01-023 case: a parallel
     * with power=8000/counter=1000 vs the base's power=6000/counter=null
     * turned out to be a genuine scrape error, not a real alternate
     * printing). 'life' replaces 'cost' here when the record is a
     * Leader, per the Leader raw.cost -> life normalization applied
     * before this comparison runs -- see normalizeLeaderCost().
     */
    public const SEVERE_GAMEPLAY_FIELDS = ['name', 'category', 'colors', 'cost', 'counter', 'power', 'attributes'];

    /**
     * Wording-only fields: genuinely vary between print runs (Bandai
     * issues real errata/text clarifications on reprints) and are common
     * enough (26 base numbers) that treating them as severe would block
     * far more than it protects.
     */
    public const TEXT_GAMEPLAY_FIELDS = ['effect', 'trigger'];

    /**
     * Leaders have no play-cost in the real rules (they start in play);
     * punk-records' 'cost' field on a Leader record is actually its
     * printed Life total, confirmed by a 203-Leader (later re-verified
     * 324-record) audit: 100% coverage, distribution concentrated at
     * 4-5, and every outlier spot-checked against known real values
     * (Edward.Newgate/Whitebeard=6, ST10-002 Luffy=3, Vegapunk=2).
     *
     * Must run BEFORE severe-field conflict detection, so 'cost'
     * divergence across a Leader's printings is compared as 'life', not
     * silently skipped or miscompared against a field that doesn't
     * apply to Leaders at all.
     */
    public static function normalizeLeaderCost(array $gameplay): array
    {
        if (($gameplay['category'] ?? null) === 'Leader') {
            $gameplay['life'] = $gameplay['cost'] ?? null;
            $gameplay['cost'] = null;
        } else {
            $gameplay['life'] = null;
        }

        return $gameplay;
    }

    /** Which SEVERE_GAMEPLAY_FIELDS entries actually apply to this record's category -- 'cost' becomes 'life' for Leaders. */
    public static function severeFieldsFor(array $gameplay): array
    {
        $fields = self::SEVERE_GAMEPLAY_FIELDS;
        if (($gameplay['category'] ?? null) === 'Leader') {
            $fields = array_map(fn ($f) => $f === 'cost' ? 'life' : $f, $fields);
        }

        return $fields;
    }

    /** Bandai's site leaks a handful of typographic character variants (fullwidth minus, U+2212 minus) inconsistently between scrapes of the same value -- cosmetically different, not a real content difference. */
    public static function normalizeText(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(["\xef\xbc\x8d", "\xe2\x88\x92"], '-', $value);
        }
        if (is_array($value)) {
            return array_map([self::class, 'normalizeText'], $value);
        }

        return $value;
    }

    /** Case/whitespace-insensitive, order-insensitive comparison key for a types array -- distinguishes a cosmetic scrape artifact (casing/script) from a genuinely different tag set. */
    public static function normalizeTypesForComparison(array $types): string
    {
        $normalized = array_map(fn ($t) => mb_strtolower(trim((string) $t)), $types);
        sort($normalized);

        return json_encode($normalized);
    }

    /**
     * Compares two normalized gameplay records (already passed through
     * normalizeLeaderCost) for the same base number. Returns
     * ['severe' => bool, 'text' => bool, 'severe_fields' => [...]].
     * 'types' is deliberately handled separately from the blunt severe
     * list -- see normalizeTypesForComparison's docblock.
     */
    public static function compare(array $existing, array $comparable): array
    {
        $severeFields = self::severeFieldsFor($existing);
        $severeDiff = false;
        $severeDiffFields = [];
        foreach ($severeFields as $f) {
            if (json_encode($existing[$f] ?? null) !== json_encode($comparable[$f] ?? null)) {
                $severeDiff = true;
                $severeDiffFields[] = $f;
            }
        }

        $textDiff = false;
        foreach (self::TEXT_GAMEPLAY_FIELDS as $f) {
            if (json_encode($existing[$f] ?? null) !== json_encode($comparable[$f] ?? null)) {
                $textDiff = true;

                break;
            }
        }

        $existingTypes = $existing['types'] ?? [];
        $comparableTypes = $comparable['types'] ?? [];
        if (json_encode($existingTypes) !== json_encode($comparableTypes)) {
            if (self::normalizeTypesForComparison($existingTypes) !== self::normalizeTypesForComparison($comparableTypes)) {
                $severeDiff = true;
                $severeDiffFields[] = 'types';
            } else {
                $textDiff = true;
            }
        }

        return ['severe' => $severeDiff, 'text' => $textDiff, 'severe_fields' => $severeDiffFields];
    }

    /**
     * Applies one base number's rows from supplemental_gameplay_overrides.csv
     * to a resolved gameplay record. Mirrors ImportOnePiece::applyGameplayOverrides()
     * exactly -- same value coercion (cost/power/counter -> int,
     * types/colors/attributes -> JSON array), same "override corrects the
     * record but doesn't erase why one was needed" contract (the caller
     * decides what to do with the returned $appliedFields for its own
     * report).
     *
     * @param  array<int, array{field: string, value: string, source_provider: string, source_url: string, verification_source: string, notes: string}>  $overrideRows  rows for THIS base number only
     * @return array{0: array, 1: array} [resolved gameplay record, applied-field provenance entries]
     */
    public static function applyOverrides(array $gameplay, array $overrideRows): array
    {
        $applied = [];
        foreach ($overrideRows as $row) {
            $field = $row['field'];
            $from = $gameplay[$field] ?? null;
            $to = $row['value'] !== '' ? $row['value'] : null;
            if (in_array($field, ['cost', 'power', 'counter'], true) && $to !== null) {
                $to = (int) $to;
            } elseif (in_array($field, ['types', 'colors', 'attributes'], true) && $to !== null) {
                $decoded = json_decode($to, true);
                $to = is_array($decoded) ? $decoded : [$to];
            }
            $gameplay[$field] = $to;
            $applied[] = [
                'field' => $field,
                'from' => $from,
                'to' => $to,
                'source_provider' => $row['source_provider'],
                'source_url' => $row['source_url'],
                'verification_source' => $row['verification_source'],
                'notes' => $row['notes'],
            ];
        }

        return [$gameplay, $applied];
    }

    /** @return array<string, array<int, array<string, string>>> base_number -> override rows, straight from the CSV */
    public static function loadOverrideRows(string $csvPath): array
    {
        if (! is_file($csvPath)) {
            return [];
        }

        $rows = [];
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $assoc = array_combine($header, $row);
            $rows[$assoc['base_number']][] = $assoc;
        }
        fclose($handle);

        return $rows;
    }
}
