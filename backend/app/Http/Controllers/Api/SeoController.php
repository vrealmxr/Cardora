<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageSeoSetting;
use Illuminate\Http\JsonResponse;

class SeoController extends Controller
{
    /**
     * Admin-editable meta title/description/H1 per page_key, for the current locale.
     * Cacheable (unlike /bootstrap) since it's the same for every visitor of a given locale.
     */
    public function __invoke(): JsonResponse
    {
        $settings = PageSeoSetting::query()
            ->where('locale', app()->getLocale())
            ->get()
            ->mapWithKeys(fn (PageSeoSetting $setting) => [
                $setting->page_key => [
                    'metaTitle' => $setting->meta_title,
                    'metaDescription' => $setting->meta_description,
                    'h1' => $setting->h1,
                    'h2' => $setting->h2,
                ],
            ]);

        return response()->json(
            ['data' => $settings],
            200,
            // Vary is required here: the response depends on the X-Locale
            // request header (set by SetLocale middleware), not the URL --
            // without it, any cache (browser or CDN) that only keys on the
            // URL will happily serve one locale's response to the other.
            // Confirmed live: the browser was reusing a cached Greek
            // response for an English page for up to 30s.
            ['Cache-Control' => 'public, max-age=30', 'Vary' => 'X-Locale'],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
