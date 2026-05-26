<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DrawCampaign;
use App\Models\DrawEntry;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformVolumeCampaignService
{
    public function syncAll(?Collection $campaigns = null): Collection
    {
        $campaigns ??= DrawCampaign::query()
            ->where('campaign_type', 'platform_volume')
            ->orderBy('created_at')
            ->get();

        return $campaigns
            ->map(fn (DrawCampaign $campaign) => $this->syncCampaign($campaign))
            ->values();
    }

    public function syncCampaign(DrawCampaign $campaign): DrawCampaign
    {
        if ($campaign->campaign_type !== 'platform_volume') {
            return $campaign;
        }

        return DB::transaction(function () use ($campaign) {
            $lockedCampaign = DrawCampaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->getKey());

            $this->backfillCompletedOrders($lockedCampaign);

            $orders = $this->qualifyingOrdersQuery($lockedCampaign)
                ->get(['id', 'buyer_id', 'order_number', 'total_amount', 'released_at', 'completed_at']);

            $validOrderIds = [];
            $entriesPerEuro = max((int) ($lockedCampaign->entries_per_euro ?? 1), 1);

            foreach ($orders as $order) {
                $amount = round((float) ($order->total_amount ?? 0), 2);
                $entries = $this->calculateEntriesForAmount($amount, $entriesPerEuro);
                $validOrderIds[] = (int) $order->getKey();

                DrawEntry::query()->updateOrCreate(
                    [
                        'draw_campaign_id' => $lockedCampaign->getKey(),
                        'order_id' => $order->getKey(),
                        'source_type' => 'platform_volume',
                    ],
                    [
                        'user_id' => $order->buyer_id,
                        'entries' => $entries,
                        'amount' => $amount,
                        'source_reference' => $order->order_number,
                        'status' => 'confirmed',
                        'metadata' => [
                            'campaign_type' => 'platform_volume',
                            'synced_from_order_release' => true,
                            'released_at' => optional($order->released_at)->toIso8601String(),
                        ],
                        'entered_at' => $order->released_at ?? $order->completed_at ?? now(),
                    ]
                );
            }

            DrawEntry::query()
                ->where('draw_campaign_id', $lockedCampaign->getKey())
                ->where('source_type', 'platform_volume')
                ->when(
                    $validOrderIds !== [],
                    fn (Builder $query) => $query->where(function (Builder $staleQuery) use ($validOrderIds) {
                        $staleQuery
                            ->whereNull('order_id')
                            ->orWhereNotIn('order_id', $validOrderIds);
                    }),
                    fn (Builder $query) => $query
                )
                ->delete();

            $metrics = DrawEntry::query()
                ->where('draw_campaign_id', $lockedCampaign->getKey())
                ->where('source_type', 'platform_volume')
                ->where('status', '!=', 'cancelled')
                ->get(['user_id', 'entries', 'amount']);

            $currentAmount = round((float) $metrics->sum('amount'), 2);
            $entriesIssued = (int) $metrics->sum('entries');
            $participantsCount = (int) $metrics->pluck('user_id')->filter()->unique()->count();

            $lockedCampaign->update([
                'current_amount' => $currentAmount,
                'entries_issued' => $entriesIssued,
                'sold_entries' => $entriesIssued,
                'participants_count' => $participantsCount,
            ]);

            return $lockedCampaign->fresh(['entries']);
        });
    }

    protected function backfillCompletedOrders(DrawCampaign $campaign): void
    {
        $this->qualifyingOrdersQuery($campaign)
            ->whereNull('completed_at')
            ->update([
                'completed_at' => DB::raw('released_at'),
            ]);
    }

    protected function qualifyingOrdersQuery(DrawCampaign $campaign): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::Released->value)
            ->whereNotNull('released_at')
            ->whereHas('items', fn (Builder $query) => $query->whereNotNull('listing_id'))
            ->where('released_at', '>=', $campaign->created_at)
            ->when(
                $campaign->ends_at !== null,
                fn (Builder $query) => $query->where('released_at', '<=', $campaign->ends_at)
            );
    }

    protected function calculateEntriesForAmount(float $amount, int $entriesPerEuro): int
    {
        return max((int) floor(max($amount, 0) * $entriesPerEuro), 0);
    }
}
