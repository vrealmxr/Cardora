<?php

namespace App\Services\Gameplay;

/**
 * The locked gameplay_data envelope shape (binder_cards.gameplay_data):
 *
 *   {schema_version, source, source_version, resolved_at, provenance: [], data: {}}
 *
 * `source`/`source_version` name the primary upstream feed and are never
 * replaced wholesale just because `provenance` records a supplemental
 * override on 1-2 fields. `data` is game-native vocabulary (see each
 * Policy) -- absent key = concept not applicable, null = applicable but
 * unknown, [] = applicable collection confirmed empty. Never put envelope
 * metadata (schema_version/source/...) at the same level as `data` --
 * that's exactly the collision `data` nesting exists to prevent.
 */
final class GameplayEnvelope
{
    public function __construct(
        public readonly int $schemaVersion,
        public readonly string $source,
        public readonly string $sourceVersion,
        public readonly string $resolvedAt,
        public readonly array $provenance,
        public readonly array $data,
    ) {}

    /** Drops any key whose value is null -- this envelope uses omission for "not applicable", never a literal null. */
    public static function omitNulls(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null);
    }

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'source' => $this->source,
            'source_version' => $this->sourceVersion,
            'resolved_at' => $this->resolvedAt,
            'provenance' => $this->provenance,
            'data' => $this->data,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
