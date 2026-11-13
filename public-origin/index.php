<?php
declare(strict_types=1);

// Dedicated origin ingress: only anonymous, signed public-HTML reads.
// No general API, admin, scanner, upload or authenticated application routes.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$deny = static function (): never { http_response_code(403); exit('Forbidden'); };
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$locale = $_GET['locale'] ?? '';
$time = $_SERVER['HTTP_X_CARDORA_PUBLIC_TIME'] ?? '';
$visitor = $_SERVER['HTTP_X_CARDORA_PUBLIC_VISITOR'] ?? '';
$signature = $_SERVER['HTTP_X_CARDORA_PUBLIC_SIGNATURE'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
    || !is_string($path) || !preg_match('~^/index\.php/api/public-html/([a-f0-9]{64})$~D', $path, $match)
    || !in_array($locale, ['el', 'en'], true) || count($_GET) !== 1
    || !preg_match('/^\d{10}$/D', $time) || abs(time() - (int) $time) > 60
    || !preg_match('/^[a-f0-9]{64}$/D', $visitor) || !preg_match('/^[a-f0-9]{64}$/D', $signature)
    || isset($_SERVER['HTTP_COOKIE']) || isset($_SERVER['HTTP_AUTHORIZATION'])
    || strlen($_SERVER['HTTP_X_CARDORA_RENDER_REQUEST'] ?? '') > 7000) $deny();
$backend = '/home/u912666299/domains/cardora.gr/public_html/backend';
$secret = trim((string) file_get_contents($backend.'/storage/app/private/public-render-signing.key'));
if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) $deny();
$expected = hash_hmac('sha256', implode("\n", ['v1', $time, 'GET', 'public-html/'.$match[1], $locale, $visitor]), $secret);
if (!hash_equals($expected, $signature)) $deny();
$client = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
if (!filter_var($client, FILTER_VALIDATE_IP)
    || !hash_equals(hash_hmac('sha256', "cardora-public-visitor-v1\n".$client, $secret), $visitor)) $deny();
unset($secret, $expected);
header_remove('X-Robots-Tag');
// Preserve the ordinary API middleware and its signed visitor rate limit.
require $backend.'/public/index.php';
