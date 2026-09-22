<?php

namespace App\Services;

use App\Mail\MarketplaceEventMail;
use App\Models\Listing;
use App\Models\User;

class FollowerListingNotificationService
{
    public function __construct(
        protected MarketplaceNotificationService $notifications
    ) {
    }

    public function notifyIfNeeded(Listing $listing): void
    {
        if (! $listing->exists) {
            return;
        }

        if (! in_array((string) $listing->status, ['active', 'published'], true)) {
            return;
        }

        if ($listing->followers_notified_at !== null) {
            return;
        }

        $listing->loadMissing(['seller', 'product']);

        $seller = $listing->seller;
        if (! $seller) {
            $this->markAsNotified($listing);

            return;
        }

        $followers = User::query()
            ->whereIn(
                'id',
                $seller->profileFollowersReceived()
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id !== (int) $seller->getKey())
                    ->unique()
                    ->values()
                    ->all()
            )
            ->get();

        if ($followers->isNotEmpty()) {
            foreach ($followers as $follower) {
                $copy = $this->copyForFollower($follower, $seller, $listing);

                $this->notifications->createForUser(
                    (int) $follower->getKey(),
                    'followed_seller_listing',
                    $copy['notification_title'],
                    $copy['notification_body'],
                    [
                        'seller_id' => (int) $seller->getKey(),
                        'listing_id' => (int) $listing->getKey(),
                        'product_slug' => $listing->product?->slug,
                    ],
                    'follows'
                );

                $this->sendFollowerEmail($follower, $seller, $listing, $copy);
            }
        }

        $this->markAsNotified($listing);
    }

    protected function markAsNotified(Listing $listing): void
    {
        $listing->forceFill([
            'followers_notified_at' => now(),
        ])->saveQuietly();
    }

    protected function sendFollowerEmail(User $follower, User $seller, Listing $listing, array $copy): void
    {
        $locale = $follower->locale === 'en' ? 'en' : 'el';
        $sellerName = $seller->display_name ?: $seller->handle ?: $seller->name;
        $listingTitle = $listing->title_snapshot ?: $listing->product?->title ?: 'Listing';
        $details = [
            [
                'label' => $locale === 'en' ? 'Seller' : 'Πωλητής',
                'value' => $sellerName,
            ],
            [
                'label' => $copy['listing_label'] ?? ($locale === 'en' ? 'Listing' : 'Αγγελία'),
                'value' => $listingTitle,
            ],
        ];

        if ($listing->price !== null) {
            $details[] = [
                'label' => $locale === 'en' ? 'Price' : 'Τιμή',
                'value' => number_format((float) $listing->price, 2, ',', '.').' EUR',
            ];
        }

        $this->notifications->sendEmailIfAllowed(
            $follower,
            new MarketplaceEventMail(
                recipient: $follower,
                content: [
                    'subject' => $copy['subject'] ?? 'Cardora',
                    'eyebrow' => $copy['eyebrow'] ?? 'Cardora',
                    'title' => $copy['title'] ?? 'Cardora',
                    'body' => $copy['body'] ?? '',
                    'details' => $details,
                    'cta' => $copy['cta'] ?? ($locale === 'en' ? 'Open listing' : 'Άνοιγμα αγγελίας'),
                    'url' => $this->listingUrl($listing),
                    'footer' => $copy['footer'] ?? '',
                ]
            ),
            'follows'
        );
    }

    protected function listingUrl(Listing $listing): string
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');
        $slug = $listing->product?->slug;

        return $slug
            ? rtrim($baseUrl, '/').'/proion/'.$slug
            : rtrim($baseUrl, '/').'/profil';
    }

    protected function copyForFollower(User $follower, User $seller, Listing $listing): array
    {
        $locale = $follower->locale === 'en' ? 'en' : 'el';
        $sellerName = $seller->display_name ?: $seller->handle ?: $seller->name;
        $listingTitle = $listing->title_snapshot ?: $listing->product?->title ?: 'Listing';

        if ($locale === 'en') {
            return [
                'notification_title' => sprintf('New listing from %s', $sellerName),
                'notification_body' => sprintf('%s just added %s.', $sellerName, $listingTitle),
                'subject' => sprintf('%s added a new listing on Cardora', $sellerName),
                'eyebrow' => 'Collector update',
                'title' => sprintf('%s has something new live', $sellerName),
                'body' => sprintf('A seller you follow just published a new listing on Cardora: %s.', $listingTitle),
                'listing_label' => 'New listing',
                'cta' => 'Open listing',
                'footer' => 'You are receiving this because you follow this seller on Cardora.',
            ];
        }

        return [
            'notification_title' => sprintf('Νέα αγγελία από τον %s', $sellerName),
            'notification_body' => sprintf('Ο %s μόλις πρόσθεσε το %s.', $sellerName, $listingTitle),
            'subject' => sprintf('Ο %s ανέβασε νέα αγγελία στην Cardora', $sellerName),
            'eyebrow' => 'Collector update',
            'title' => sprintf('Ο %s ανέβασε κάτι νέο', $sellerName),
            'body' => sprintf('Ένας χρήστης που ακολουθείς μόλις δημοσίευσε νέα αγγελία στην Cardora: %s.', $listingTitle),
            'listing_label' => 'Νέα αγγελία',
            'cta' => 'Άνοιγμα αγγελίας',
            'footer' => 'Λαμβάνεις αυτό το email επειδή ακολουθείς αυτόν τον πωλητή στην Cardora.',
        ];
    }
}
