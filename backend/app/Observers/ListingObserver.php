<?php

namespace App\Observers;

use App\Models\Listing;
use App\Services\BinderPriceHistoryService;
use App\Services\BinderSetAlertService;
use App\Services\FollowerListingNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

class ListingObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected FollowerListingNotificationService $followerNotifications,
        protected BinderSetAlertService $binderAlerts,
        protected BinderPriceHistoryService $priceHistory
    ) {
    }

    public function created(Listing $listing): void
    {
        try {
            $this->followerNotifications->notifyIfNeeded($listing);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $this->binderAlerts->notifyIfNeeded($listing);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->recordPricePointIfActive($listing);
    }

    public function updated(Listing $listing): void
    {
        $statusChanged = $listing->wasChanged('status');
        $publishedAtAdded = $listing->getOriginal('published_at') === null && $listing->published_at !== null;

        if (! $statusChanged && ! $publishedAtAdded) {
            return;
        }

        try {
            $this->followerNotifications->notifyIfNeeded($listing);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $this->binderAlerts->notifyIfNeeded($listing);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->recordPricePointIfActive($listing);
    }

    protected function recordPricePointIfActive(Listing $listing): void
    {
        if (! in_array((string) $listing->status, ['active', 'published'], true)) {
            return;
        }

        try {
            $this->priceHistory->recordListingPricePoint($listing);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
