<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicPageDataService;
use Illuminate\Http\Request;

class PublicPageDataController extends Controller
{
    public function __invoke(Request $request, PublicPageDataService $service)
    {
        $locale = $request->query('locale', 'el');
        abort_unless(in_array($locale, ['el', 'en'], true), 422);
        // Intentionally ignores authentication. Never vary this public response by a user/session.
        return response()->json(['data' => $service->snapshot($locale)], 200,
            ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
