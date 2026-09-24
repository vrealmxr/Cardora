<?php

namespace App\Services\Gameplay\Policies;

use App\Services\Gameplay\Contracts\GameplayPolicy;
use App\Services\Gameplay\GameplayEnvelope;

/**
 * Magic: The Gathering, source Scryfall. card_key already encodes the
 * Scryfall id ("magic:scryfall:{id}"), so matching is a direct 1:1
 * reconstruction -- no conflict concept exists here (each Scryfall id is
 * exactly one printing == exactly one canonical card).
 */
class MagicGameplayPolicy implements GameplayPolicy
{
    public function __construct(private readonly string $sourceVersion) {}

    public function game(): string
    {
        return 'magic';
    }

    public function source(): string
    {
        return 'scryfall';
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
        $handle = gzopen($snapshotPath, 'r');
        while (($line = gzgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $c = json_decode($line, true);
            if (! is_array($c) || empty($c['id'])) {
                yield ['card_key' => null, 'source_id' => null, 'fields' => [], 'matched' => false, 'malformed' => true];

                continue;
            }
            $cardKey = "magic:scryfall:{$c['id']}";
            $matched = isset($canonicalIndex[$cardKey]);
            yield [
                'card_key' => $matched ? $cardKey : null,
                'source_id' => $c['id'],
                'fields' => $matched ? $this->normalize($c) : [],
                'matched' => $matched,
                'malformed' => false,
            ];
        }
        gzclose($handle);
    }

    private function normalize(array $c): array
    {
        $faces = $c['card_faces'] ?? null;
        if ($faces) {
            return [
                'type_line' => $c['type_line'] ?? null,
                'color_identity' => $c['color_identity'] ?? [],
                'keywords' => $c['keywords'] ?? [],
                'faces' => array_map(fn ($f) => GameplayEnvelope::omitNulls([
                    'name' => $f['name'] ?? null,
                    'mana_cost' => ($f['mana_cost'] ?? '') !== '' ? $f['mana_cost'] : null,
                    'type_line' => $f['type_line'] ?? null,
                    'oracle_text' => $f['oracle_text'] ?? null,
                    'colors' => $f['colors'] ?? null,
                    'power' => $f['power'] ?? null,
                    'toughness' => $f['toughness'] ?? null,
                    'loyalty' => $f['loyalty'] ?? null,
                ]), $faces),
            ];
        }

        return GameplayEnvelope::omitNulls([
            'mana_cost' => ($c['mana_cost'] ?? '') !== '' ? $c['mana_cost'] : null,
            'cmc' => $c['cmc'] ?? null,
            'type_line' => $c['type_line'] ?? null,
            'oracle_text' => $c['oracle_text'] ?? null,
            'power' => $c['power'] ?? null,
            'toughness' => $c['toughness'] ?? null,
            'loyalty' => $c['loyalty'] ?? null,
            'colors' => $c['colors'] ?? [],
            'color_identity' => $c['color_identity'] ?? [],
            'keywords' => $c['keywords'] ?? [],
        ]);
    }

    public function severeFields(array $normalizedFields): array
    {
        return []; // 1:1 match, no aggregation, no conflict concept
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
