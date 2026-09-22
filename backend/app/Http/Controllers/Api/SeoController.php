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
                ],
            ]);

        return response()->json(
            ['data' => $settings],
            200,
            ['Cache-Control' => 'public, max-age=30'],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
