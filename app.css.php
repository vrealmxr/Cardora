<?php

declare(strict_types=1);

$patterns = [
    __DIR__ . '/backend/public/static/index-*.css',
    __DIR__ . '/backend/public/assets/index-*.css',
    __DIR__ . '/assets/index-*.css',
];

$matches = [];
foreach ($patterns as $pattern) {
    foreach (glob($pattern) ?: [] as $file) {
        if (is_file($file)) {
            $matches[] = $file;
        }
    }
}

usort($matches, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

$stylesheet = $matches[0] ?? null;

if ($stylesheet === null || !is_file($stylesheet)) {
    http_response_code(404);
    exit;
}

$css = file_get_contents($stylesheet);
if ($css === false) {
    http_response_code(500);
    exit;
}

$css = preg_replace_callback('/url\(([^)]+)\)/i', static function (array $match): string {
    $raw = trim($match[1]);
    $quote = '';

    if (($raw[0] ?? '') === '"' || ($raw[0] ?? '') === "'") {
        $quote = $raw[0];
        $raw = trim($raw, "\"'");
    }

    if ($raw === '' || str_starts_with($raw, 'data:') || str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
        return $match[0];
    }

    $path = parse_url($raw, PHP_URL_PATH) ?: $raw;
    $file = basename($path);
    if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
        return $match[0];
    }

    $proxied = '/asset.php?f=' . rawurlencode($file);
    return 'url(' . $quote . $proxied . $quote . ')';
}, $css);

header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo $css;
