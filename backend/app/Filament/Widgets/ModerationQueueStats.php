<?php

namespace App\Filament\Widgets;

use App\Models\BlogPost;
use App\Models\Listing;
use App\Models\SupportTicket;
use App\Models\VerificationSubmission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ModerationQueueStats extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $metrics = Cache::remember('filament:moderation-queue-stats', now()->addSeconds(30), function (): array {
            return [
                'listings_pending_review' => Listing::whereIn('status', ['pending_review', 'needs_revision'])->count(),
                'verification_queue' => VerificationSubmission::whereIn('status', ['submitted', 'under_review', 'needs_revision'])->count(),
                'open_support_tickets' => SupportTicket::whereIn('status', ['open', 'investigating', 'waiting_on_user'])->count(),
                'blog_posts_in_review' => BlogPost::where('status', 'review')->count(),
            ];
        });

        return [
            Stat::make('Listings pending review', (string) $metrics['listings_pending_review'])
                ->description('Seller submissions waiting for moderation')
                ->color('warning'),
            Stat::make('Verification queue', (string) $metrics['verification_queue'])
                ->description('Identity / address / bank reviews')
                ->color('warning'),
            Stat::make('Open support tickets', (string) $metrics['open_support_tickets'])
                ->description('Tickets requiring staff attention')
                ->color('danger'),
            Stat::make('Blog posts in review', (string) $metrics['blog_posts_in_review'])
                ->description('Editorial workflow backlog')
                ->color('info'),
        ];
    }
}
