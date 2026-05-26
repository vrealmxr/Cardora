<?php

declare(strict_types=1);

$matches = glob(__DIR__ . '/static/index-*.js') ?: [];
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
