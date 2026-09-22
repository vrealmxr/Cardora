<?php

namespace App\Filament\Widgets;

use App\Services\MarketplaceFinanceSnapshotService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketplaceFinanceStats extends BaseWidget
{
    public static function canView(): bool
    {
        return ! (auth()->user()?->is_seo_editor ?? false);
    }

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $metrics = app(MarketplaceFinanceSnapshotService::class)->snapshot();
        $feeDescription = $metrics['fees_synced']
            ? 'Stripe fees synced from captured charges'
            : 'Stripe fees unavailable, using local totals only';

        return [
            Stat::make('Gross commission', $this->formatCurrency($metrics['gross_commission']))
                ->description((string) $metrics['orders_count'] . ' paid marketplace orders')
                ->color('warning'),
            Stat::make('Stripe fees', $this->formatCurrency($metrics['stripe_fees']))
                ->description($feeDescription)
                ->color($metrics['fees_synced'] ? 'danger' : 'gray'),
            Stat::make('Net Cardora revenue', $this->formatCurrency($metrics['net_revenue']))
                ->description('Gross commission minus Stripe processing fees')
                ->color('success'),
            Stat::make('Held seller funds', $this->formatCurrency($metrics['held_funds']))
                ->description('Protected hold not yet released to sellers')
                ->color('info'),
            Stat::make('Released to sellers', $this->formatCurrency($metrics['released_to_sellers']))
                ->description('Transfers already released to connected accounts')
                ->color('primary'),
        ];
    }

    protected function formatCurrency(float $amount): string
    {
        return number_format($amount, 2) . ' EUR';
    }
}
