<?php

namespace App\Services\Gameplay;

use App\Services\Gameplay\Contracts\GameplayPolicy;
use App\Services\Gameplay\Policies\OnePieceGameplayPolicy;

/**
 * The one shared pipeline every GameplayPolicy runs through:
 *   loadSnapshot (policy) -> matchCanonicalCard (policy) -> normalize (policy)
 *   -> detectConflicts (here, generic) -> applyOverrides (policy, where it exists)
 *   -> validateEnvelope (here) -> buildGameplayData (policy)
 *
 * Never touches binder_cards/binder_card_variants itself -- it returns a
 * report plus a card_key => GameplayEnvelope map, and the caller (the
 * Artisan command) decides whether to write anything at all.
 */
class GameplayResolver
{
    public function __construct(private readonly GameplayPolicy $policy) {}

    /**
     * @param  array<int, array{id:int, card_key:string, card_type:?string}>  $canonicalCards
     * @return array{report: array, envelopes: array<string, GameplayEnvelope>}
     */
    public function run(string $snapshotPath, array $canonicalCards, array $externalIdRows, array $variantRows, string $resolvedAt): array
    {
        $canonicalIndex = $this->policy->buildCanonicalIndex($canonicalCards, $externalIdRows, $variantRows);
        $canonicalKeys = array_column($canonicalCards, 'card_key');

        $byCardKey = []; // card_key => [[source_id, fields], ...]
        $sourceObjectsTotal = 0;
        $unmatchedCount = 0;
        $ambiguousCount = 0;
        $malformedCount = 0;

        foreach ($this->policy->iterateSourceObjects($snapshotPath, $canonicalIndex) as $obj) {
            if ($obj['malformed'] ?? false) {
                $malformedCount++;

                continue;
            }
            $sourceObjectsTotal++;
            if (! empty($obj['ambiguous'])) {
                $ambiguousCount++;

                continue;
            }
            if (! $obj['matched']) {
                $unmatchedCount++;

                continue;
            }
            $byCardKey[$obj['card_key']][] = [$obj['source_id'], $obj['fields']];
        }

        $matched = array_keys($byCardKey);
        $conflictsFound = 0;
        $conflictsResolved = 0;
        $unresolvedConflicts = 0;
        $overridesApplied = 0;
        $envelopes = [];

        foreach ($byCardKey as $cardKey => $records) {
            $first = $records[0][1];
            $severeDiffFields = [];
            foreach (array_slice($records, 1) as [$sourceId, $fields]) {
                $severe = $this->policy->severeFields($first);
                foreach ($severe as $f) {
                    if (json_encode($first[$f] ?? null) !== json_encode($fields[$f] ?? null)) {
                        $severeDiffFields[$f] = true;
                    }
                }
            }

            $resolved = $first;
            $provenance = [];

            if (! empty($severeDiffFields)) {
                $conflictsFound++;

                $overrideRows = [];
                if ($this->policy instanceof OnePieceGameplayPolicy) {
                    $baseNumber = OnePieceGameplayPolicy::baseNumberOf($records[0][0]);
                    $overrideRows = $this->policy->overrideRowsFor($baseNumber);
                }

                if (! empty($overrideRows)) {
                    [$resolved, $applied] = OnePieceGameplayNormalizer::applyOverrides($resolved, $overrideRows);
                    $appliedFields = array_column($applied, 'field');
                    $overridesApplied += count(array_intersect($appliedFields, array_keys($severeDiffFields)));
                    $stillUnresolved = array_diff(array_keys($severeDiffFields), $appliedFields);
                    if (empty($stillUnresolved)) {
                        $conflictsResolved++;
                    } else {
                        $unresolvedConflicts++;
                    }
                    if (! empty($applied)) {
                        $provenance[] = ['type' => 'supplemental_gameplay_override', 'base_number' => $baseNumber, 'fields' => $appliedFields];
                    }
                } else {
                    $unresolvedConflicts++;
                }
            }

            $envelopes[$cardKey] = new GameplayEnvelope(
                schemaVersion: 1,
                source: $this->policy->source(),
                sourceVersion: $this->policy->sourceVersion(),
                resolvedAt: $resolvedAt,
                provenance: $provenance,
                data: $this->policy->buildData($resolved),
            );
        }

        $report = [
            'canonical_cards_total' => count($canonicalKeys),
            'source_objects_total' => $sourceObjectsTotal,
            'matched_canonical_cards' => count($matched),
            'gameplay_records_generated' => count($envelopes),
            'canonical_cards_without_gameplay' => count($canonicalKeys) - count($matched),
            'source_objects_without_canonical_match' => $unmatchedCount,
            'ambiguous_matches' => $ambiguousCount,
            'gameplay_conflicts_found' => $conflictsFound,
            'gameplay_conflicts_resolved' => $conflictsResolved,
            'unresolved_gameplay_conflicts' => $unresolvedConflicts,
            'overrides_applied' => $overridesApplied,
            'malformed_payloads' => $malformedCount,
            'cards_that_would_be_created' => 0,
            'variants_that_would_be_created' => 0,
            'canonical_identity_changes' => 0,
        ];

        return ['report' => $report, 'envelopes' => $envelopes];
    }
}
