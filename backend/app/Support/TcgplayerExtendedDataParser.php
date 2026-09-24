<?php

namespace App\Support;

/**
 * Parses TCGplayer's `extendedData` blob -- a Python-repr-style array of
 * {'name': ..., 'displayName': ..., 'value': ...} objects (single-quoted,
 * not valid JSON) attached to every card row in the cardora-sets-data CSV
 * export.
 *
 * Extracted out of ImportBinderCatalog (where it lived as a private method
 * matching on the wrong key -- see the regression test) so every importer
 * that touches this same TCGplayer format reuses one fixed, tested
 * implementation instead of each carrying its own copy of the same bug.
 */
class TcgplayerExtendedDataParser
{
    /**
     * `name` is TCGplayer's compact, no-space internal key (e.g.
     * `CardType`); `displayName` is the human-readable label (`Card Type`)
     * -- for most fields the two are identical (`Number`, `Rarity`), so a
     * regex matching on `name` happened to work by accident, but for any
     * field whose compact key drops a space (`CardType` vs `Card Type`,
     * confirmed on Dragon Ball Super's real extendedData) it silently
     * never matched, returning null for 100% of rows. Matching on
     * `displayName` instead is correct for both cases and never a
     * regression for the fields that already worked.
     */
    public static function field(string $extendedData, string $displayName): ?string
    {
        $pattern = "/\\{[^{}]*?'displayName':\\s*'".preg_quote($displayName, '/')."'[^{}]*?'value':\\s*'([^']*)'/";

        if (preg_match($pattern, $extendedData, $matches) === 1) {
            // Short flavor fields only (a rarity name, a type word, a card number).
            // Truncated defensively rather than trusting the regex never over-matches
            // into a much longer field -- safer than widening the column.
            $value = trim(mb_substr($matches[1], 0, 190));

            return $value !== '' ? $value : null;
        }

        return null;
    }
}
