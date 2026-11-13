<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EbayPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EbayPriceController extends Controller
{
    public function __construct(private readonly EbayPriceService $ebay)
    {
    }

    /**
     * GET /api/scanner/price?q=Charizard ex 199/165
     * Price estimate the AI Scanner shows for an identified card.
     */
    public function show(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'category_ids' => ['nullable', 'string', 'regex:/^[0-9,]+$/'],
        ]);

        if (! $this->ebay->isConfigured()) {
            return response()->json(['message' => 'eBay price lookups are not enabled.'], 503);
        }

        try {
            $options = $request->has('category_ids') ? ['category_ids' => $validated['category_ids'] ?? null] : [];

            return response()->json(['data' => $this->ebay->estimate($validated['q'], $options)]);
        } catch (RuntimeException $exception) {
            Log::warning('eBay price lookup failed', ['q' => $validated['q'], 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Could not fetch eBay prices right now.'], 502);
        }
    }
}
