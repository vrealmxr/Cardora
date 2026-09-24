<?php

namespace App\Services\Gameplay\Policies;

use App\Services\Gameplay\Contracts\GameplayPolicy;
use App\Services\Gameplay\GameplayEnvelope;
use App\Services\Gameplay\OnePieceGameplayNormalizer;

/**
 * One Piece TCG, source buhbbl/punk-records (a mirror of the same
 * en.onepiece-cardgame.com data ImportOnePiece imports from, pinned to a
 * specific commit -- see the snapshot manifest; not proven identical to
 * the exact commit the canonical v1 import used, since that SHA was
 * never recorded, so this is labeled onepiece-gameplay-snapshot-v1, not
 * a claim of being the original import's exact source).
 *
 * Matching goes through binder_card_variants.source_variant_id (the raw
 * punk-records id, already stored per variant by the canonical
 * importer) -- never re-deriving home-pack/set assignment. Several raw
 * records (base + every _p/_r suffix) can map to the same card_key, so
 * this is the one policy where conflict detection is real: it defers
 * entirely to OnePieceGameplayNormalizer, the same class ImportOnePiece
 * itself uses, so the two commands can never classify a conflict
 * differently.
 */
class OnePieceGameplayPolicy implements GameplayPolicy
{
    public function __construct(
        private readonly string $sourceVersion,
        private readonly array $overrideRowsByBase, // base_number -> override CSV rows, from OnePieceGameplayNormalizer::loadOverrideRows()
    ) {}

    public function game(): string
    {
        return 'onepiece';
    }

    public function source(): string
    {
        return 'onepiece-cardgame (via buhbbl/punk-records mirror)';
    }

    public function sourceVersion(): string
    {
        return $this->sourceVersion;
    }

    /** @return array{variant_map: array<string, string>} */
    public function buildCanonicalIndex(array $canonicalCards, array $externalIdRows, array $variantRows): array
    {
        $variantMap = [];
        foreach ($variantRows as $row) {
            if (! empty($row['source_variant_id'])) {
                $variantMap[$row['source_variant_id']] = $row['card_key'];
            }
        }

        return ['variant_map' => $variantMap];
    }

    public function iterateSourceObjects(string $snapshotPath, array $canonicalIndex): iterable
    {
        $variantMap = $canonicalIndex['variant_map'];
        foreach (glob(rtrim($snapshotPath, '/').'/english/cards/*/*.json') as $path) {
            $raw = json_decode(file_get_contents($path), true);
            $sid = $raw['id'] ?? null;
            if (! $sid) {
                yield ['card_key' => null, 'source_id' => null, 'fields' => [], 'matched' => false, 'malformed' => true];

                continue;
            }
            $cardKey = $variantMap[$sid] ?? null;
            yield [
                'card_key' => $cardKey,
                'source_id' => $sid,
                'fields' => $cardKey ? $this->normalize($raw) : [],
                'matched' => $cardKey !== null,
                'malformed' => false,
            ];
        }
    }

    private function normalize(array $raw): array
    {
        return OnePieceGameplayNormalizer::normalizeLeaderCost(OnePieceGameplayNormalizer::normalizeText([
            'name' => $raw['name'] ?? '',
            'category' => $raw['category'] ?? '',
            'colors' => $raw['colors'] ?? [],
            'cost' => $raw['cost'] ?? null,
            'counter' => $raw['counter'] ?? null,
            'power' => $raw['power'] ?? null,
            'effect' => $raw['effect'] ?? '',
            'trigger' => $raw['trigger'] ?? null,
            'types' => $raw['types'] ?? [],
            'attributes' => $raw['attributes'] ?? [],
        ]));
    }

    /** base_number for a punk-records source id, e.g. "OP07-091_p1" -> "OP07-091" -- same split ImportOnePiece::baseNumberOf uses. */
    public static function baseNumberOf(string $sourceId): string
    {
        return explode('_', $sourceId)[0];
    }

    public function overrideRowsFor(string $baseNumber): array
    {
        return $this->overrideRowsByBase[$baseNumber] ?? [];
    }

    public function severeFields(array $normalizedFields): array
    {
        return OnePieceGameplayNormalizer::severeFieldsFor($normalizedFields);
    }

    public function textFields(): array
    {
        return OnePieceGameplayNormalizer::TEXT_GAMEPLAY_FIELDS;
    }

    public function buildData(array $resolvedFields): array
    {
        $data = [
            'category' => $resolvedFields['category'] ?? null,
            'colors' => $resolvedFields['colors'] ?? [],
            'attributes' => $resolvedFields['attributes'] ?? [],
            'types' => $resolvedFields['types'] ?? [],
            'effect' => $resolvedFields['effect'] ?? null,
            'cost' => $resolvedFields['cost'] ?? null,
            'power' => $resolvedFields['power'] ?? null,
            'counter' => $resolvedFields['counter'] ?? null,
            'trigger' => $resolvedFields['trigger'] ?? null,
            'life' => $resolvedFields['life'] ?? null,
        ];

        return GameplayEnvelope::omitNulls($data);
    }
}
