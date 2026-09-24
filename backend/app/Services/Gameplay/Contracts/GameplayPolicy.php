<?php

namespace App\Services\Gameplay\Contracts;

/**
 * One implementation per game, all driven by the same GameplayResolver
 * pipeline: loadSnapshot -> matchCanonicalCard -> normalize ->
 * detectConflicts -> applyOverrides -> validateEnvelope -> buildGameplayData.
 * A policy never touches the database and never decides canonical
 * identity -- it only turns one game's raw snapshot objects into
 * candidate (card_key => normalized gameplay fields) pairs and, from a
 * resolved field set, the envelope's `data` object.
 */
interface GameplayPolicy
{
    public function game(): string;

    public function source(): string;

    /**
     * Builds whatever lookup structure this game's matching needs from
     * the canonical rows GameplayResolver already loaded from the DB
     * (read-only). E.g. Magic/Pokemon: a card_key => true set (the key
     * is directly reconstructible from the raw object's own id). YGO: a
     * set_code => card_key map (from binder_card_external_ids). One
     * Piece: a source_variant_id => card_key map (from
     * binder_card_variants).
     *
     * @param  array<int, array{id:int, card_key:string, card_type:?string}>  $canonicalCards
     */
    public function buildCanonicalIndex(array $canonicalCards, array $externalIdRows, array $variantRows): array;

    /**
     * Streams the raw snapshot and yields one entry per raw (object,
     * matched card_key) pair as:
     *   ['card_key' => string, 'source_id' => string, 'fields' => array, 'matched' => bool]
     * `matched=false` entries (source_objects_without_canonical_match)
     * are yielded too, with card_key=null, so the caller can count them
     * without a second pass over the file.
     */
    public function iterateSourceObjects(string $snapshotPath, array $canonicalIndex): iterable;

    /** Which of $normalizedFields' keys count as a severe conflict when 2+ source objects map to the same card_key. Empty array = this game never aggregates multiple source objects per card (no conflict concept). */
    public function severeFields(array $normalizedFields): array;

    /** Wording-only fields: a mismatch here is reported but never blocks resolution. */
    public function textFields(): array;

    /** Resolved, conflict-free fields (after any override application) -> the envelope's `data` object, applying this game's absent/null/[] discipline. */
    public function buildData(array $resolvedFields): array;

    public function sourceVersion(): string;
}
