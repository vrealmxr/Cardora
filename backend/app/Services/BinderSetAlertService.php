<?php

namespace App\Services;

use App\Mail\MarketplaceEventMail;
use App\Models\BinderCard;
use App\Models\BinderUserCard;
use App\Models\BinderWatchedSet;
use App\Models\Listing;
use App\Models\User;

class BinderSetAlertService
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

        if ($listing->binder_alert_notified_at !== null) {
            return;
        }

        $listing->loadMissing(['seller', 'product.binderCard.set', 'product.binderCard.game']);

        $card = $listing->product?->binderCard;
        if (! $card || ! $card->set_id) {
            return;
        }

        $sellerId = (int) $listing->seller_id;

        $ownerIds = BinderUserCard::query()
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->where('binder_cards.set_id', $card->set_id)
            ->pluck('binder_user_cards.user_id');

        $watcherIds = BinderWatchedSet::query()
            ->where('set_id', $card->set_id)
            ->pluck('user_id');

        $recipientIds = $ownerIds->merge($watcherIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id !== $sellerId)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            $this->markAsNotified($listing);

            return;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        foreach ($recipients as $recipient) {
            $copy = $this->copyForRecipient($recipient, $card, $listing);

            $this->notifications->createForUser(
                (int) $recipient->getKey(),
                'binder_set_card_listed',
                $copy['notification_title'],
                $copy['notification_body'],
                [
                    'listing_id' => (int) $listing->getKey(),
                    'product_slug' => $listing->product?->slug,
                    'binder_card_id' => (int) $card->getKey(),
                    'binder_set_id' => (int) $card->set_id,
                    'price' => (float) $listing->price,
                ],
                'binder_alerts'
            );

            $this->sendEmail($recipient, $card, $listing, $copy);
        }

        $this->markAsNotified($listing);
    }

    protected function markAsNotified(Listing $listing): void
    {
        $listing->forceFill([
            'binder_alert_notified_at' => now(),
        ])->saveQuietly();
    }

    protected function sendEmail(User $recipient, BinderCard $card, Listing $listing, array $copy): void
    {
        $locale = $recipient->locale === 'en' ? 'en' : 'el';
        $listingTitle = $listing->title_snapshot ?: $listing->product?->title ?: $card->name;

        $details = [
            [
                'label' => $locale === 'en' ? 'Card' : 'Κάρτα',
                'value' => $listingTitle,
            ],
            [
                'label' => $locale === 'en' ? 'Set' : 'Σετ',
                'value' => $card->set?->name ?? '—',
            ],
            [
                'label' => $locale === 'en' ? 'Price' : 'Τιμή',
                'value' => number_format((float) $listing->price, 2, ',', '.').' EUR',
            ],
        ];

        $this->notifications->sendEmailIfAllowed(
            $recipient,
            new MarketplaceEventMail(
                recipient: $recipient,
                content: [
                    'subject' => $copy['subject'],
                    'eyebrow' => $copy['eyebrow'],
                    'title' => $copy['title'],
                    'body' => $copy['body'],
                    'details' => $details,
                    'cta' => $copy['cta'],
                    'url' => $this->listingUrl($listing),
                    'footer' => $copy['footer'],
                ]
            ),
            'binder_alerts'
        );
    }

    protected function listingUrl(Listing $listing): string
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');
        $slug = $listing->product?->slug;

        return $slug
            ? rtrim($baseUrl, '/').'/proion/'.$slug
            : rtrim($baseUrl, '/').'/cardora-binder';
    }

    protected function copyForRecipient(User $recipient, BinderCard $card, Listing $listing): array
    {
        $locale = $recipient->locale === 'en' ? 'en' : 'el';
        $setName = $card->set?->name ?? '';
        $priceText = number_format((float) $listing->price, 2, ',', '.').'€';

        if ($locale === 'en') {
            return [
                'notification_title' => sprintf('%s just listed', $card->name),
                'notification_body' => sprintf('New listing from "%s" at %s. Tap to view.', $setName, $priceText),
                'subject' => sprintf('%s was just listed on Cardora', $card->name),
                'eyebrow' => 'Binder alert',
                'title' => sprintf('%s is up for sale', $card->name),
                'body' => sprintf('A card from a set you track just went live on Cardora: %s (%s) at %s.', $card->name, $setName, $priceText),
                'cta' => 'Open listing',
                'footer' => 'You are receiving this because you own or watch this set in Cardora Binder.',
            ];
        }

        return [
            'notification_title' => sprintf('Ανέβηκε η κάρτα %s', $card->name),
            'notification_body' => sprintf('Νέα αγγελία από το "%s" στα %s. Πάτα για να τη δεις.', $setName, $priceText),
            'subject' => sprintf('Η κάρτα %s μόλις καταχωρήθηκε στην Cardora', $card->name),
            'eyebrow' => 'Ειδοποίηση Binder',
            'title' => sprintf('Η κάρτα %s είναι προς πώληση', $card->name),
            'body' => sprintf('Μια κάρτα από ένα σετ που παρακολουθείς μόλις ανέβηκε στην Cardora: %s (%s) στα %s.', $card->name, $setName, $priceText),
            'cta' => 'Άνοιγμα αγγελίας',
            'footer' => 'Λαμβάνεις αυτό το email επειδή έχεις ή παρακολουθείς αυτό το σετ στο Cardora Binder.',
        ];
    }
}
