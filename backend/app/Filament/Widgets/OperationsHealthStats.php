<?php

namespace App\Filament\Widgets;

use App\Models\AuctionBid;
use App\Models\DrawCampaign;
use App\Models\EscrowTransaction;
use App\Models\Notification;
use App\Models\Payout;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class OperationsHealthStats extends BaseWidget
{
    public static function canView(): bool
    {
        return ! (auth()->user()?->is_seo_editor ?? false);
    }

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $metrics = Cache::remember('filament:operations-health-stats', now()->addSeconds(30), function (): array {
            return [
                'escrow_still_held' => EscrowTransaction::where('status', 'held')->count(),
                'pending_payouts' => Payout::whereIn('status', ['pending', 'queued', 'processing'])->count(),
                'unread_notifications' => Notification::whereNull('read_at')->count(),
                'live_promos' => DrawCampaign::where('status', 'active')->count() + AuctionBid::query()->distinct('listing_id')->count('listing_id'),
            ];
        });

        return [
            Stat::make('Escrow still held', (string) $metrics['escrow_still_held'])
                ->description('Orders still waiting for release')
                ->color('warning'),
            Stat::make('Pending payouts', (string) $metrics['pending_payouts'])
                ->description('Seller disbursements awaiting action')
                ->color('danger'),
            Stat::make('Unread notifications', (string) $metrics['unread_notifications'])
                ->description('System notifications not yet opened')
                ->color('info'),
            Stat::make('Live promos', (string) $metrics['live_promos'])
                ->description('Live draws plus auction activity')
                ->color('success'),
        ];
    }
}
