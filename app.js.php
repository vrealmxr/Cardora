<?php

declare(strict_types=1);

$patterns = [
    __DIR__ . '/backend/public/static/index-*.js',
    __DIR__ . '/backend/public/assets/index-*.js',
    __DIR__ . '/assets/index-*.js',
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

$bundle = $matches[0] ?? null;

if ($bundle === null || !is_file($bundle)) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
readfile($bundle);
