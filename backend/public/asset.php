<?php

declare(strict_types=1);

$requested = $_GET['f'] ?? '';
$file = basename((string) $requested);

if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(400);
    exit;
}

$candidates = [
    __DIR__ . '/' . $file,
    __DIR__ . '/icons/' . $file,
    __DIR__ . '/static/' . $file,
];

$source = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $source = $candidate;
        break;
    }
}

if ($source === null) {
    http_response_code(404);
    exit;
}

$mimeByExt = [
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'woff2' => 'font/woff2',
    'woff' => 'font/woff',
    'ttf' => 'font/ttf',
    'otf' => 'font/otf',
];

$ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
$mime = $mimeByExt[$ext] ?? (mime_content_type($source) ?: 'application/octet-stream');

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($source));
readfile($source);
