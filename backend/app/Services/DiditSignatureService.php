<?php

namespace App\Services;

class DiditSignatureService
{
    public function verify(string $raw, string $signature, string $timestamp, string $secret): bool
    {
        if ($secret === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300
            || ! preg_match('/^[a-f0-9]{64}$/D', $signature)) {
            return false;
        }
        try {
            // Preserve empty objects and numeric-looking object keys.
            $body = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
            if (! $body instanceof \stdClass || (string) ($body->timestamp ?? '') !== $timestamp) {
                return false;
            }
            $canonical = json_encode($this->canonicalize($body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
            return hash_equals(hash_hmac('sha256', $canonical, $secret), $signature);
        } catch (\JsonException $e) {
            return false;
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $result = new \stdClass();
            foreach ($properties as $key => $item) {
                $result->{$key} = $this->canonicalize($item);
            }
            return $result;
        }
        if (is_array($value)) {
            return array_map([$this, 'canonicalize'], $value);
        }
        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (int) $value;
        }
        return $value;
    }
}
