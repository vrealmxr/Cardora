<?php

namespace App\Observers;

use App\Models\Listing;
use App\Services\FollowerListingNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

class ListingObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected FollowerListingNotificationService $followerNotifications
    ) {
    }

    public function created(Listing $listing): void
    {
        try {
            $this->followerNotifications->notifyIfNeeded($listing);
        } catch (Throwable $exception) {
            report($exception);
        }
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
    }
}
