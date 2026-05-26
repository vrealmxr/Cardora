<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDrawEntryRequest;
use App\Http\Resources\DrawEntryResource;
use App\Models\DrawCampaign;
use App\Services\DrawEntryService;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;

class DrawEntryController extends Controller
{
    public function index(DrawCampaign $drawCampaign, Request $request)
    {
        $isAdmin = (bool) $request->user()?->is_admin;
        abort_if(! $isAdmin && $drawCampaign->campaign_type !== 'platform_volume', 404);

        $entries = $drawCampaign->entries()
            ->with(['user', 'order'])
            ->when(
                $request->filled('user_id'),
                fn ($query) => $query->where('user_id', $request->integer('user_id'))
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
            )
            ->when(
                $request->filled('source_type'),
                fn ($query) => $query->where('source_type', $request->string('source_type'))
            )
            ->latest('entered_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return DrawEntryResource::collection($entries);
    }

    public function store(
        StoreDrawEntryRequest $request,
        DrawCampaign $drawCampaign,
        DrawEntryService $drawEntries,
        MarketplaceNotificationService $notifications
    )
    {
        $isAdmin = (bool) $request->user()?->is_admin;
        abort_if(! $isAdmin && $drawCampaign->campaign_type !== 'platform_volume', 403);

        $validated = $request->validated();
        $entry = $drawEntries->createConfirmedEntry(
            $request->user(),
            $drawCampaign,
            (int) $validated['entries'],
            $validated
        );

        $notifications->createForUser(
            $request->user()->getKey(),
            'draw_entry_confirmed',
            __('api.notifications.draw_entry_title'),
            __('api.notifications.draw_entry_body'),
            ['draw_campaign_id' => $drawCampaign->getKey(), 'entry_id' => $entry->getKey()],
            'orders'
        );

        return response()->json([
            'message' => __('api.draws.entry_created'),
            'data' => new DrawEntryResource($entry),
        ], 201);
    }
}
