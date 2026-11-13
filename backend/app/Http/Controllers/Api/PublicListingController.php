<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicListingService;
use Illuminate\Http\Request;

class PublicListingController extends Controller
{
    private function json($data, int $status = 200)
    {
        return response()->json(['data' => $data], $status,
            ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function show(Request $request, PublicListingService $service, string $slug)
    {
        $locale = $request->query('locale', 'el');
        abort_unless(in_array($locale, ['el', 'en'], true), 422);
        $listing = $service->findPublic($slug, $locale);
        return $this->json($listing, $listing ? 200 : 404);
    }

    public function paths(PublicListingService $service)
    {
        return $this->json($service->publicPaths());
    }
}
