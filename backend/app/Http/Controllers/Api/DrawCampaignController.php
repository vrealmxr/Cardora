<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDrawCampaignRequest;
use App\Http\Requests\UpdateDrawCampaignRequest;
use App\Http\Resources\DrawCampaignResource;
use App\Models\DrawCampaign;
use App\Services\MarketplaceAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DrawCampaignController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = (bool) $request->user()?->is_admin;

        $drawCampaigns = DrawCampaign::query()
            ->with(['hostUser', 'winner', 'prizeListing'])
            ->withCount('entries')
            ->when(
                $request->string('search')->isNotEmpty(),
                function ($query) use ($request) {
                    $search = $request->string('search');
                    $query->where(function ($builder) use ($search) {
                        $builder
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('prize_title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $request->filled('campaign_type'),
                fn ($query) => $query->where('campaign_type', $request->string('campaign_type'))
            )
            ->when(! $isAdmin, fn ($query) => $query->where('campaign_type', 'platform_volume'))
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
            )
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('status', 'active')
            )
            ->when(
                $request->boolean('featured_only'),
                fn ($query) => $query->where('featured', true)
            )
            ->when(
                $request->filled('host_user_id'),
                fn ($query) => $query->where('host_user_id', $request->integer('host_user_id'))
            )
            ->orderByDesc('featured')
            ->orderByRaw('CASE WHEN draw_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('draw_at')
            ->orderBy('ends_at')
            ->paginate($request->integer('per_page', 12));

        return DrawCampaignResource::collection($drawCampaigns);
    }

    public function store(StoreDrawCampaignRequest $request, MarketplaceAccessService $marketplaceAccess)
    {
        abort_unless((bool) $request->user()?->is_admin, 403, __('api.errors.forbidden'));

        $marketplaceAccess->assertCanSell($request->user(), app()->getLocale());

        $payload = $request->validated();
        $payload['host_user_id'] = $request->user()->getKey();
        $payload['status'] = ($payload['status'] ?? 'draft') === 'draft' ? 'draft' : 'review';
        $payload['slug'] = $this->resolveSlug(
            $payload['slug'] ?? null,
            $payload['title']
        );

        $drawCampaign = DrawCampaign::create($payload);

        return (new DrawCampaignResource($drawCampaign->load(['hostUser', 'winner', 'prizeListing'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(DrawCampaign $drawCampaign)
    {
        $isAdmin = (bool) request()->user()?->is_admin;
        abort_if(! $isAdmin && $drawCampaign->campaign_type !== 'platform_volume', 404);

        return new DrawCampaignResource(
            $drawCampaign->load(['hostUser', 'winner', 'prizeListing'])->loadCount('entries')
        );
    }

    public function update(
        UpdateDrawCampaignRequest $request,
        DrawCampaign $drawCampaign,
        MarketplaceAccessService $marketplaceAccess
    )
    {
        abort_unless((bool) $request->user()?->is_admin, 403, __('api.errors.forbidden'));

        $marketplaceAccess->assertCanSell($request->user(), app()->getLocale());

        $payload = $request->validated();

        if (array_key_exists('status', $payload)) {
            $payload['status'] = $payload['status'] === 'draft' ? 'draft' : 'review';
        }

        if (array_key_exists('title', $payload) || array_key_exists('slug', $payload)) {
            $payload['slug'] = $this->resolveSlug(
                $payload['slug'] ?? $drawCampaign->slug,
                $payload['title'] ?? $drawCampaign->title,
                $drawCampaign->getKey()
            );
        }

        $drawCampaign->update($payload);

        return new DrawCampaignResource(
            $drawCampaign->fresh()->load(['hostUser', 'winner', 'prizeListing'])->loadCount('entries')
        );
    }

    public function destroy(DrawCampaign $drawCampaign)
    {
        abort_unless((bool) request()->user()?->is_admin, 403, __('api.errors.forbidden'));

        $drawCampaign->delete();

        return response()->noContent();
    }

    protected function resolveSlug(?string $requestedSlug, string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($requestedSlug ?: $title);
        $normalizedBaseSlug = $baseSlug !== '' ? $baseSlug : 'draw-campaign';
        $slug = $normalizedBaseSlug;
        $counter = 2;

        while (
            DrawCampaign::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$normalizedBaseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
