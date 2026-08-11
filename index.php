<?php

declare(strict_types=1);

function renderSitePinGate(?string $errorMessage = null): void
{
    $currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $redirectTo = $currentUri !== '' ? $currentUri : '/';
    $escapedRedirect = htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8');
    $escapedError = $errorMessage !== null
        ? htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8')
        : '';

    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!doctype html><html lang="el"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Cardora Access</title>';
    echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;min-height:100vh;align-items:center;justify-content:center}';
    echo '.card{width:min(92vw,420px);background:#111827;border:1px solid #334155;border-radius:14px;padding:24px;box-shadow:0 18px 40px rgba(0,0,0,.35)}';
    echo 'h1{margin:0 0 10px;font-size:22px}p{margin:0 0 16px;color:#94a3b8;font-size:14px;line-height:1.5}.error{color:#fca5a5;margin-bottom:10px;font-size:13px}';
    echo 'label{display:block;font-size:13px;margin-bottom:6px}.pin{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid #475569;background:#020617;color:#f8fafc}';
    echo 'button{margin-top:12px;width:100%;padding:10px 12px;border-radius:10px;border:0;background:#22c55e;color:#052e16;font-weight:700;cursor:pointer}';
    echo '</style></head><body><main class="card"><h1>Cardora Private Access</h1><p>Η πλατφόρμα είναι προσωρινά κλειδωμένη. Βάλε PIN για πρόσβαση.</p>';

    if ($escapedError !== '') {
        echo '<div class="error">'.$escapedError.'</div>';
    }

    echo '<form method="post" action="/__site-unlock">';
    echo '<input type="hidden" name="redirect_to" value="'.$escapedRedirect.'">';
    echo '<label for="site_pin">PIN</label><input class="pin" id="site_pin" type="password" name="site_pin" autocomplete="off" required>';
    echo '<button type="submit">Unlock</button></form></main></body></html>';
}

$sitePinLockEnabled = false;
$sitePin = '1234CARD';
$sitePinCookieName = 'cardora_site_unlock';
$sitePinCookieValue = hash('sha256', $sitePin.'|cardora');
$sitePinUnlockPath = '/__site-unlock';
$sitePinBypassPatterns = [
    '#^/api/stripe/webhooks(/|$)#',
    '#^/icons/(?:site\.webmanifest|favicon\.ico|favicon-16x16\.png|favicon-32x32\.png|apple-touch-icon\.png|android-chrome-192x192\.png|android-chrome-512x512\.png)$#',
];

if (
    ! isset($_SERVER['HTTP_AUTHORIZATION']) &&
    isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
) {
    $_SERVER['HTTP_AUTHORIZATION'] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$requestedPathForLock = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($sitePinLockEnabled) {
    $isBypassed = false;

    foreach ($sitePinBypassPatterns as $pattern) {
        if (preg_match($pattern, $requestedPathForLock) === 1) {
            $isBypassed = true;
            break;
        }
    }

    if (! $isBypassed) {
        $hasValidLockCookie = isset($_COOKIE[$sitePinCookieName])
            && hash_equals($sitePinCookieValue, (string) $_COOKIE[$sitePinCookieName]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $requestedPathForLock === $sitePinUnlockPath) {
            $submittedPin = isset($_POST['site_pin']) ? trim((string) $_POST['site_pin']) : '';
            $redirectTo = isset($_POST['redirect_to']) ? (string) $_POST['redirect_to'] : '/';

            if ($submittedPin !== '' && hash_equals($sitePin, $submittedPin)) {
                $isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

                setcookie($sitePinCookieName, $sitePinCookieValue, [
                    'expires' => time() + (60 * 60 * 24 * 30),
                    'path' => '/',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                if (! str_starts_with($redirectTo, '/')) {
                    $redirectTo = '/';
                }

                header('Location: '.$redirectTo, true, 302);
                exit;
            }

            renderSitePinGate('Λάθος PIN. Δοκίμασε ξανά.');
            exit;
        }

        if (! $hasValidLockCookie) {
            renderSitePinGate();
            exit;
        }
    }
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = $requestPath;
if (str_starts_with($normalizedPath, '/index.php/')) {
    $normalizedPath = '/' . ltrim(substr($normalizedPath, strlen('/index.php/')), '/');
}

if (preg_match('#^/(api|sanctum|admin|livewire)(/|$)#', $normalizedPath) === 1) {
    $_SERVER['REQUEST_URI'] = $normalizedPath . ((isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') ? ('?' . $_SERVER['QUERY_STRING']) : '');
    $_SERVER['SCRIPT_NAME'] = '/backend/public/index.php';
    $_SERVER['PHP_SELF'] = '/backend/public/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/backend/public/index.php';
    require __DIR__ . '/backend/public/index.php';
    exit;
}

if (preg_match('#^/(stripe/connect/(refresh|success)|email/verify)(/|$)#', $normalizedPath) === 1) {
    $_SERVER['REQUEST_URI'] = $normalizedPath . ((isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') ? ('?' . $_SERVER['QUERY_STRING']) : '');
    $_SERVER['SCRIPT_NAME'] = '/backend/public/index.php';
    $_SERVER['PHP_SELF'] = '/backend/public/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/backend/public/index.php';
    require __DIR__ . '/backend/public/index.php';
    exit;
}

$backendPublicRoot = realpath(__DIR__ . '/backend/public');
if ($backendPublicRoot !== false) {
    $normalizedPathForFile = rawurldecode($normalizedPath);
    if ($normalizedPathForFile !== '/' && !str_contains($normalizedPathForFile, "\0")) {
        $candidatePath = realpath($backendPublicRoot . '/' . ltrim($normalizedPathForFile, '/'));
        if (
            $candidatePath !== false &&
            str_starts_with($candidatePath, $backendPublicRoot . DIRECTORY_SEPARATOR) &&
            is_file($candidatePath) &&
            strtolower(pathinfo($candidatePath, PATHINFO_EXTENSION)) !== 'php'
        ) {
            $ext = strtolower(pathinfo($candidatePath, PATHINFO_EXTENSION));
            $mimeByExt = [
                'js' => 'application/javascript; charset=UTF-8',
                'mjs' => 'application/javascript; charset=UTF-8',
                'css' => 'text/css; charset=UTF-8',
                'map' => 'application/json; charset=UTF-8',
                'json' => 'application/json; charset=UTF-8',
                'webmanifest' => 'application/manifest+json; charset=UTF-8',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'ico' => 'image/x-icon',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'woff2' => 'font/woff2',
                'woff' => 'font/woff',
                'ttf' => 'font/ttf',
                'otf' => 'font/otf',
            ];
            $mime = $mimeByExt[$ext] ?? (mime_content_type($candidatePath) ?: 'application/octet-stream');
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($candidatePath));
            $cacheControl = str_starts_with($normalizedPathForFile, '/static/')
                ? 'public, max-age=31536000, immutable'
                : 'public, max-age=604800, stale-while-revalidate=86400';
            header('Cache-Control: ' . $cacheControl);
            header('X-Content-Type-Options: nosniff');
            readfile($candidatePath);
            exit;
        }
    }
}

if ($normalizedPath === '/favicon.ico') {
    $fallbackFavicon = __DIR__ . '/backend/public/icons/favicon.ico';
    if (is_file($fallbackFavicon)) {
        header('Content-Type: image/x-icon');
        header('Content-Length: ' . (string) filesize($fallbackFavicon));
        readfile($fallbackFavicon);
        exit;
    }
}

if ($requestPath === '/app.js.php') {
    require __DIR__ . '/app.js.php';
    exit;
}

if ($requestPath === '/app.css.php') {
    require __DIR__ . '/app.css.php';
    exit;
}

if ($requestPath === '/asset.php') {
    require __DIR__ . '/asset.php';
    exit;
}

if (str_starts_with($normalizedPath, '/storage/')) {
    $relativePath = ltrim(substr($normalizedPath, strlen('/storage/')), '/');
    $relativePath = str_replace(['../', '..\\'], '', $relativePath);
    $file = __DIR__ . '/backend/storage/app/public/' . $relativePath;

    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }

    $mime = mime_content_type($file) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($file));
    readfile($file);
    exit;
}

if (preg_match('/\.(?:js|mjs|css|map|json|ico|png|jpe?g|svg|webp|gif|woff2?|ttf|otf|webmanifest)$/i', $normalizedPath) === 1) {
    http_response_code(404);
    exit;
}

$spaIndex = __DIR__ . '/backend/public/index.html';

if (is_file($spaIndex)) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    readfile($spaIndex);
    exit;
}

http_response_code(500);
echo 'Missing SPA index file.';
