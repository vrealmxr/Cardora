<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

class AdminKeyValueState
{
    /**
     * Convert nested arrays/objects into a flat key-value map for Filament KeyValue.
     *
     * Example:
     * ['shipping' => ['domestic' => ['provider' => 'BoxNow']]]
     * becomes:
     * ['shipping.domestic.provider' => 'BoxNow']
     */
    public static function flattenForForm(mixed $state): array
    {
        if (! is_array($state)) {
            if ($state instanceof Arrayable) {
                $state = $state->toArray();
            } elseif ($state instanceof JsonSerializable) {
                $serialized = $state->jsonSerialize();
                $state = is_array($serialized) ? $serialized : [];
            } else {
                return [];
            }
        }

        $result = [];
        self::flattenInto($state, '', $result);

        return $result;
    }

    /**
     * Convert flat dotted keys back to nested arrays for storage.
     *
     * Example:
     * ['shipping.domestic.provider' => 'BoxNow']
     * becomes:
     * ['shipping' => ['domestic' => ['provider' => 'BoxNow']]]
     */
    public static function inflateForStorage(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $result = [];

        foreach ($state as $flatKey => $rawValue) {
            if (! is_string($flatKey)) {
                continue;
            }

            $flatKey = trim($flatKey);

            if ($flatKey === '') {
                continue;
            }

            $segments = array_values(array_filter(explode('.', $flatKey), static fn (string $segment): bool => $segment !== ''));

            if ($segments === []) {
                continue;
            }

            self::setBySegments($result, $segments, self::coerceScalar($rawValue));
        }

        return self::normalizeNumericArrays($result);
    }

    private static function flattenInto(array $state, string $prefix, array &$result): void
    {
        foreach ($state as $key => $value) {
            $segment = (string) $key;
            $path = $prefix === '' ? $segment : "{$prefix}.{$segment}";

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            } elseif ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            } elseif ($value instanceof Stringable) {
                $value = (string) $value;
            } elseif (is_object($value)) {
                $value = method_exists($value, '__toString') ? (string) $value : get_class($value);
            }

            if (is_array($value)) {
                if ($value === []) {
                    $result[$path] = null;

                    continue;
                }

                self::flattenInto($value, $path, $result);

                continue;
            }

            $result[$path] = self::stringifyScalar($value);
        }
    }

    private static function stringifyScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private static function setBySegments(array &$target, array $segments, mixed $value): void
    {
        $cursor = &$target;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $normalizedSegment = ctype_digit($segment) ? (int) $segment : $segment;

            if ($index === $lastIndex) {
                $cursor[$normalizedSegment] = $value;

                return;
            }

            if (! isset($cursor[$normalizedSegment]) || ! is_array($cursor[$normalizedSegment])) {
                $cursor[$normalizedSegment] = [];
            }

            $cursor = &$cursor[$normalizedSegment];
        }
    }

    private static function normalizeNumericArrays(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalizeNumericArrays($item);
        }

        if (self::isSequentialIntKeyArray($normalized)) {
            ksort($normalized);

            return array_values($normalized);
        }

        return $normalized;
    }

    private static function isSequentialIntKeyArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        $keys = array_keys($value);

        foreach ($keys as $key) {
            if (! is_int($key)) {
                return false;
            }
        }

        sort($keys);

        return $keys === range(0, count($keys) - 1);
    }

    private static function coerceScalar(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::normalizeNumericArrays($value);
        }

        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $raw = (string) $value;
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        $lower = strtolower($trimmed);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if ($lower === 'null') {
            return null;
        }

        if (preg_match('/^-?(?:0|[1-9]\d*)$/', $trimmed) === 1) {
            return (int) $trimmed;
        }

        if (preg_match('/^-?(?:\d+\.\d+|\d+,\d+)$/', $trimmed) === 1) {
            return (float) str_replace(',', '.', $trimmed);
        }

        return $raw;
    }
}

