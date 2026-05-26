<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdminOverviewStats extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $metrics = Cache::remember('filament:admin-overview-stats', now()->addSeconds(30), function (): array {
            return [
                'users' => User::count(),
                'verified_sellers' => User::where('is_verified_seller', true)->count(),
                'published_listings' => Listing::where('status', 'published')->count(),
                'gross_order_volume' => (float) Order::sum('total'),
                'category_count' => Category::count(),
            ];
        });

        return [
            Stat::make('Registered users', (string) $metrics['users'])
                ->description('Total marketplace accounts')
                ->color('primary'),
            Stat::make('Verified sellers', (string) $metrics['verified_sellers'])
                ->description('Seller accounts with trust badge')
                ->color('success'),
            Stat::make('Published listings', (string) $metrics['published_listings'])
                ->description('Public marketplace listings')
                ->color('info'),
            Stat::make('Gross order volume', number_format($metrics['gross_order_volume'], 2) . ' EUR')
                ->description((string) $metrics['category_count'] . ' categories currently active')
                ->color('warning'),
        ];
    }
}
