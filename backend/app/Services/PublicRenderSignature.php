<?php

namespace App\Services;

use Illuminate\Http\Request;

/** Rate attribution only. Never authenticates a user or changes visibility. */
class PublicRenderSignature
{
    public static function verifiedVisitor(Request $request, ?string $secret = null, ?int $now = null): ?string
    {
        if ($request->method() !== 'GET' || $request->headers->has('Authorization') || $request->headers->has('Cookie')) return null;
        $visitor = $request->header('X-Cardora-Public-Visitor', '');
        $signature = $request->header('X-Cardora-Public-Signature', '');
        $timestamp = $request->header('X-Cardora-Public-Time', '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $visitor) || !preg_match('/^[a-f0-9]{64}$/D', $signature)
            || !preg_match('/^[0-9]{10}$/D', $timestamp) || abs(($now ?? time()) - (int) $timestamp) > 60) return null;
        $path = rawurldecode((string) parse_url($request->getRequestUri(), PHP_URL_PATH));
        $resource = preg_replace('#^/(?:index\.php/)?api/#', '', $path);
        if (!preg_match('#^(?:public-page-data|seo|public-listing-paths|public-html/[a-f0-9]{64}|(?:public-listings|profiles)/[^/?\x00-\x20\x7f\#]+)$#D', $resource)) return null;
        if (array_diff(array_keys($request->query()), ['locale'])) return null;
        $locale = $request->query('locale', 'el');
        if (!in_array($locale, ['el', 'en'], true)) return null;
        if ($secret === null) {
            $file = storage_path('app/private/public-render-signing.key');
            $secret = is_readable($file) ? trim(file_get_contents($file)) : '';
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) return null;
        $message = implode("\n", ['v1', $timestamp, 'GET', $resource, $locale, $visitor]);
        return hash_equals(hash_hmac('sha256', $message, $secret), $signature) ? $visitor : null;
    }
}
