<?php

namespace App\Services\Gameplay\Policies;

use App\Services\Gameplay\Contracts\GameplayPolicy;
use App\Services\Gameplay\GameplayEnvelope;

/**
 * Pokemon, source TCGdex (pinned tcgdex/cards-database commit, compiled
 * locally, not the live API -- see the manifest for this snapshot).
 * card_key = 'pokemon-' . strtolower(tcgdex_id), direct 1:1
 * reconstruction, same as Magic: no conflict concept.
 */
class PokemonGameplayPolicy implements GameplayPolicy
{
    public function __construct(private readonly string $sourceVersion) {}

    public function game(): string
    {
        return 'pokemon';
    }

    public function source(): string
    {
        return 'tcgdex';
    }

    public function sourceVersion(): string
    {
        return $this->sourceVersion;
    }

    public function buildCanonicalIndex(array $canonicalCards, array $externalIdRows, array $variantRows): array
    {
        $index = [];
        foreach ($canonicalCards as $c) {
            $index[$c['card_key']] = true;
        }

        return $index;
    }

    public function iterateSourceObjects(string $snapshotPath, array $canonicalIndex): iterable
    {
        $raw = json_decode(file_get_contents($snapshotPath), true);
        foreach ($raw as $c) {
            if (empty($c['id'])) {
                yield ['card_key' => null, 'source_id' => null, 'fields' => [], 'matched' => false, 'malformed' => true];

                continue;
            }
            $cardKey = 'pokemon-'.strtolower($c['id']);
            $matched = isset($canonicalIndex[$cardKey]);
            yield [
                'card_key' => $matched ? $cardKey : null,
                'source_id' => $c['id'],
                'fields' => $matched ? $this->normalize($c) : [],
                'matched' => $matched,
                'malformed' => false,
            ];
        }
    }

    private function normalize(array $c): array
    {
        $category = $c['category'] ?? null;
        if ($category === 'Pokemon') {
            return GameplayEnvelope::omitNulls([
                'hp' => $c['hp'] ?? null,
                'types' => $c['types'] ?? [],
                'stage' => $c['stage'] ?? null,
                'evolves_from' => $c['evolveFrom'] ?? null,
                'retreat_cost' => $c['retreat'] ?? null,
                'regulation_mark' => $c['regulationMark'] ?? null,
                'abilities' => array_map(fn ($a) => GameplayEnvelope::omitNulls([
                    'type' => $a['type'] ?? null, 'name' => $a['name'] ?? null, 'effect' => $a['effect'] ?? null,
                ]), $c['abilities'] ?? []),
                'attacks' => array_map(fn ($a) => GameplayEnvelope::omitNulls([
                    'name' => $a['name'] ?? null, 'cost' => $a['cost'] ?? [], 'damage' => $a['damage'] ?? null, 'effect' => $a['effect'] ?? null,
                ]), $c['attacks'] ?? []),
                'weaknesses' => array_map(fn ($w) => GameplayEnvelope::omitNulls(['type' => $w['type'] ?? null, 'value' => $w['value'] ?? null]), $c['weaknesses'] ?? []),
                'resistances' => array_map(fn ($w) => GameplayEnvelope::omitNulls(['type' => $w['type'] ?? null, 'value' => $w['value'] ?? null]), $c['resistances'] ?? []),
            ]);
        }
        if ($category === 'Trainer') {
            return GameplayEnvelope::omitNulls([
                'effect' => $c['effect'] ?? null,
                'trainer_type' => $c['trainerType'] ?? null,
            ]);
        }
        if ($category === 'Energy') {
            return GameplayEnvelope::omitNulls([
                'effect' => $c['effect'] ?? null,
                'energy_type' => $c['energyType'] ?? null,
            ]);
        }

        return GameplayEnvelope::omitNulls(['effect' => $c['effect'] ?? null]);
    }

    public function severeFields(array $normalizedFields): array
    {
        return [];
    }

    public function textFields(): array
    {
        return [];
    }

    public function buildData(array $resolvedFields): array
    {
        return $resolvedFields;
    }
}
