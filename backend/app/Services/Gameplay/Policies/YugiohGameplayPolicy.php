<?php

namespace App\Services\Gameplay\Policies;

use App\Services\Gameplay\Contracts\GameplayPolicy;
use App\Services\Gameplay\GameplayEnvelope;

/**
 * Yu-Gi-Oh!, source YGOPRODeck (controlled one-time fetch -- no upstream
 * bulk/versioning mechanism exists to pin to, see the snapshot manifest).
 * card_key is NOT reconstructible from the raw id (it's
 * "{setKey}-{slug(full_set_code)}", and setKey resolution is non-trivial
 * importer logic) -- matching instead goes through the SAME
 * binder_card_external_ids rows the canonical importer already wrote
 * (provider=ygoprodeck, external_type=set_code), never re-deriving
 * set/card identity independently. A raw card's card_sets array can list
 * several set_codes (several printings of the same name); each is
 * checked separately, so one raw object can match 0, 1, or many
 * canonical card_keys.
 */
class YugiohGameplayPolicy implements GameplayPolicy
{
    public function __construct(private readonly string $sourceVersion) {}

    public function game(): string
    {
        return 'yugioh';
    }

    public function source(): string
    {
        return 'ygoprodeck';
    }

    public function sourceVersion(): string
    {
        return $this->sourceVersion;
    }

    /** @return array{setcode_map: array<string, string[]>} */
    public function buildCanonicalIndex(array $canonicalCards, array $externalIdRows, array $variantRows): array
    {
        $setcodeMap = [];
        foreach ($externalIdRows as $row) {
            if (($row['provider'] ?? null) === 'ygoprodeck' && ($row['external_type'] ?? null) === 'set_code') {
                $setcodeMap[$row['external_id']][] = $row['entity_key'];
            }
        }

        return ['setcode_map' => $setcodeMap];
    }

    public function iterateSourceObjects(string $snapshotPath, array $canonicalIndex): iterable
    {
        $setcodeMap = $canonicalIndex['setcode_map'];
        $raw = json_decode(file_get_contents($snapshotPath), true)['data'];

        foreach ($raw as $c) {
            if (empty($c['id']) || empty($c['name'])) {
                yield ['card_key' => null, 'source_id' => null, 'fields' => [], 'matched' => false, 'malformed' => true];

                continue;
            }
            $codes = [];
            foreach (($c['card_sets'] ?? []) as $s) {
                if (! empty($s['set_code'])) {
                    $codes[$s['set_code']] = true;
                }
            }
            foreach (array_keys($codes) as $code) {
                $keys = $setcodeMap[$code] ?? [];
                if (count($keys) === 0) {
                    yield ['card_key' => null, 'source_id' => "{$c['id']}:{$code}", 'fields' => [], 'matched' => false, 'malformed' => false];

                    continue;
                }
                if (count($keys) > 1) {
                    yield ['card_key' => null, 'source_id' => "{$c['id']}:{$code}", 'fields' => [], 'matched' => false, 'ambiguous' => true, 'malformed' => false];

                    continue;
                }
                yield [
                    'card_key' => $keys[0],
                    'source_id' => "{$c['id']}:{$code}",
                    'fields' => $this->normalize($c),
                    'matched' => true,
                    'malformed' => false,
                ];
            }
        }
    }

    private function fieldSet(?string $frameType): array
    {
        $ft = $frameType ?? '';
        $isPendulum = str_ends_with($ft, '_pendulum');
        $base = $isPendulum ? substr($ft, 0, -strlen('_pendulum')) : $ft;

        if (in_array($base, ['spell', 'trap', 'skill'], true)) {
            return ['race'];
        }
        if ($base === 'token') {
            return ['attribute', 'race', 'atk', 'def'];
        }
        if ($base === 'link') {
            return ['attribute', 'race', 'atk', 'linkval', 'linkmarkers'];
        }
        $fields = ['attribute', 'race', 'atk', 'def', 'level'];
        if ($base === 'xyz') {
            $fields = array_diff($fields, ['level']);
            $fields[] = 'rank';
        }
        if ($isPendulum) {
            $fields[] = 'scale';
        }

        return $fields;
    }

    private function normalize(array $c): array
    {
        $fields = $this->fieldSet($c['frameType'] ?? null);
        $data = ['frame_type' => $c['frameType'] ?? null, 'description' => $c['desc'] ?? null];
        foreach ($fields as $f) {
            $data[$f] = match ($f) {
                'race' => $c['race'] ?? null,
                'attribute' => $c['attribute'] ?? null,
                'level' => $c['level'] ?? null,
                'rank' => $c['level'] ?? null, // YGOPRODeck reuses the 'level' key for Xyz Rank
                'scale' => $c['scale'] ?? null,
                'linkval' => $c['linkval'] ?? null,
                'linkmarkers' => $c['linkmarkers'] ?? null,
                'atk' => $c['atk'] ?? null,
                'def' => $c['def'] ?? null,
                default => null,
            };
        }

        return GameplayEnvelope::omitNulls($data);
    }

    public function severeFields(array $normalizedFields): array
    {
        return []; // gameplay stats don't vary by printing for YGO -- only rarity/region does, no aggregation conflict concept
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
