<?php

namespace App\Services;

use App\Models\DrawCampaign;
use App\Models\DrawEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DrawEntryService
{
    public function __construct(
        protected MarketplaceAccessService $marketplaceAccess
    ) {
    }

    public function assertCanPurchase(User $user, DrawCampaign $drawCampaign, int $entries): void
    {
        $this->validateCampaignForEntries($drawCampaign, $user, $entries);
    }

    public function createConfirmedEntry(
        User $user,
        DrawCampaign $drawCampaign,
        int $entries,
        array $attributes = []
    ): DrawEntry {
        return DB::transaction(function () use ($user, $drawCampaign, $entries, $attributes) {
            $lockedCampaign = DrawCampaign::query()
                ->lockForUpdate()
                ->findOrFail($drawCampaign->getKey());

            $this->validateCampaignForEntries($lockedCampaign, $user, $entries);

            $amount = array_key_exists('amount', $attributes)
                ? round((float) $attributes['amount'], 2)
                : round((float) ($lockedCampaign->entry_price ?? 0) * $entries, 2);

            $entry = DrawEntry::create([
                'draw_campaign_id' => $lockedCampaign->getKey(),
                'user_id' => $user->getKey(),
                'order_id' => $attributes['order_id'] ?? null,
                'entries' => $entries,
                'amount' => $amount,
                'source_type' => $attributes['source_type'] ?? 'ticket_purchase',
                'source_reference' => $attributes['source_reference'] ?? null,
                'status' => $attributes['status'] ?? 'confirmed',
                'metadata' => $attributes['metadata'] ?? null,
                'entered_at' => $attributes['entered_at'] ?? now(),
            ]);

            $hasExistingEntriesFromUser = DrawEntry::query()
                ->where('draw_campaign_id', $lockedCampaign->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', '!=', 'cancelled')
                ->whereKeyNot($entry->getKey())
                ->exists();

            $updates = [
                'entries_issued' => (int) $lockedCampaign->entries_issued + $entries,
                'current_amount' => (float) $lockedCampaign->current_amount + $amount,
            ];

            if ($lockedCampaign->campaign_type === 'community_raffle') {
                $updates['sold_entries'] = (int) $lockedCampaign->sold_entries + $entries;
            }

            if (! $hasExistingEntriesFromUser) {
                $updates['participants_count'] = (int) $lockedCampaign->participants_count + 1;
            }

            $lockedCampaign->update($updates);

            return $entry->load(['user', 'order']);
        });
    }

    protected function validateCampaignForEntries(DrawCampaign $drawCampaign, User $user, int $entries): void
    {
        $this->marketplaceAccess->assertCanBuy($user, app()->getLocale());

        if ($entries < 1) {
            throw ValidationException::withMessages([
                'entries' => [__('api.orders.invalid_items')],
            ]);
        }

        if ($drawCampaign->status !== 'active') {
            throw ValidationException::withMessages([
                'draw_campaign_id' => [__('api.draws.not_active')],
            ]);
        }

        if ((int) ($drawCampaign->host_user_id ?? 0) === (int) $user->getKey()) {
            throw ValidationException::withMessages([
                'draw_campaign_id' => [__('api.draws.cannot_join_own_draw')],
            ]);
        }

        if (
            $drawCampaign->target_entries !== null
            && (int) $drawCampaign->target_entries > 0
            && ((int) $drawCampaign->sold_entries + $entries) > (int) $drawCampaign->target_entries
        ) {
            throw ValidationException::withMessages([
                'entries' => [__('api.draws.sold_out')],
            ]);
        }

        if (
            $drawCampaign->max_entries_per_user !== null
            && (int) $drawCampaign->max_entries_per_user > 0
        ) {
            $existingEntries = (int) DrawEntry::query()
                ->where('draw_campaign_id', $drawCampaign->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', '!=', 'cancelled')
                ->sum('entries');

            if (($existingEntries + $entries) > (int) $drawCampaign->max_entries_per_user) {
                throw ValidationException::withMessages([
                    'entries' => [__('api.draws.limit_reached')],
                ]);
            }
        }
    }
}
