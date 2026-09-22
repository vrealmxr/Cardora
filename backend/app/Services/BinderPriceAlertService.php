<?php

namespace App\Services;

use App\Mail\MarketplaceEventMail;
use App\Models\BinderCard;
use App\Models\BinderCardPricePoint;
use App\Models\BinderPriceAlertState;
use App\Models\BinderUserCard;
use App\Models\BinderWatchedSet;
use App\Models\User;

/**
 * Cardora PRO perk: price-drop/rise alerts. Triggered whenever a new price
 * point is recorded for a Binder card — notifies PRO users who own or watch
 * that card's set when the price moves meaningfully since the last alert.
 */
class BinderPriceAlertService
{
    // Don't ping people over small day-to-day noise — only real moves.
    protected const ALERT_THRESHOLD = 0.10;

    public function __construct(
        protected MarketplaceNotificationService $notifications
    ) {
    }

    public function checkForAlert(BinderCardPricePoint $point): void
    {
        $state = BinderPriceAlertState::query()->find($point->binder_card_id);

        if (! $state) {
            // First price point we've ever seen for this card — nothing to
            // compare against yet, just seed the baseline silently.
            BinderPriceAlertState::create([
                'binder_card_id' => $point->binder_card_id,
                'last_alert_price' => $point->price,
                'last_alert_at' => now(),
            ]);

            return;
        }

        $previous = (float) $state->last_alert_price;
        if ($previous <= 0.0) {
            return;
        }

        $change = ((float) $point->price - $previous) / $previous;

        if (abs($change) < self::ALERT_THRESHOLD) {
            return;
        }

        $card = BinderCard::query()->with('set')->find($point->binder_card_id);
        if (! $card) {
            return;
        }

        $this->notifyWatchers($card, $point, $change > 0);

        $state->update([
            'last_alert_price' => $point->price,
            'last_alert_at' => now(),
        ]);
    }

    protected function notifyWatchers(BinderCard $card, BinderCardPricePoint $point, bool $isRise): void
    {
        $ownerIds = BinderUserCard::query()->where('card_id', $card->id)->pluck('user_id');
        $watcherIds = BinderWatchedSet::query()->where('set_id', $card->set_id)->pluck('user_id');

        // Don't tell someone their own listing/sale moved the price.
        $sellerId = $point->listing_id
            ? (int) (\App\Models\Listing::query()->whereKey($point->listing_id)->value('seller_id') ?? 0)
            : 0;

        $recipientIds = $ownerIds->merge($watcherIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id !== $sellerId)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        // Price alerts are a Cardora PRO perk — only PRO users get pinged.
        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->whereIn('pro_status', ['trialing', 'active'])
            ->get();

        foreach ($recipients as $recipient) {
            $copy = $this->copyForRecipient($recipient, $card, $point, $isRise);

            $this->notifications->createForUser(
                (int) $recipient->getKey(),
                'binder_price_alert',
                $copy['notification_title'],
                $copy['notification_body'],
                [
                    'binder_card_id' => (int) $card->getKey(),
                    'binder_set_id' => (int) $card->set_id,
                    'price' => (float) $point->price,
                    'direction' => $isRise ? 'up' : 'down',
                ],
                'price_alerts'
            );

            $this->notifications->sendEmailIfAllowed(
                $recipient,
                new MarketplaceEventMail(
                    recipient: $recipient,
                    content: [
                        'subject' => $copy['subject'],
                        'eyebrow' => $copy['eyebrow'],
                        'title' => $copy['title'],
                        'body' => $copy['body'],
                        'details' => [
                            [
                                'label' => $recipient->locale === 'en' ? 'Card' : 'Κάρτα',
                                'value' => $card->name,
                            ],
                            [
                                'label' => $recipient->locale === 'en' ? 'Set' : 'Σετ',
                                'value' => $card->set?->name ?? '—',
                            ],
                            [
                                'label' => $recipient->locale === 'en' ? 'New price' : 'Νέα τιμή',
                                'value' => number_format((float) $point->price, 2, ',', '.').' EUR',
                            ],
                        ],
                        'cta' => $recipient->locale === 'en' ? 'Open Cardora Binder' : 'Άνοιγμα Cardora Binder',
                        'url' => rtrim((string) config('app.frontend_url'), '/').'/cardora-binder',
                        'footer' => $copy['footer'],
                    ]
                ),
                'price_alerts'
            );
        }
    }

    protected function copyForRecipient(User $recipient, BinderCard $card, BinderCardPricePoint $point, bool $isRise): array
    {
        $locale = $recipient->locale === 'en' ? 'en' : 'el';
        $priceText = number_format((float) $point->price, 2, ',', '.').'€';

        if ($locale === 'en') {
            return [
                'notification_title' => $isRise
                    ? sprintf('%s price is up', $card->name)
                    : sprintf('%s price dropped', $card->name),
                'notification_body' => sprintf('Now around %s on Cardora.', $priceText),
                'subject' => sprintf('Price alert: %s', $card->name),
                'eyebrow' => 'Cardora PRO price alert',
                'title' => $isRise ? sprintf('%s is trending up', $card->name) : sprintf('%s just got cheaper', $card->name),
                'body' => sprintf('The price for %s on Cardora moved to %s.', $card->name, $priceText),
                'footer' => 'You are receiving this Cardora PRO alert because you own or watch this card/set.',
            ];
        }

        return [
            'notification_title' => $isRise
                ? sprintf('Ανέβηκε η τιμή: %s', $card->name)
                : sprintf('Έπεσε η τιμή: %s', $card->name),
            'notification_body' => sprintf('Τώρα περίπου %s στην Cardora.', $priceText),
            'subject' => sprintf('Ειδοποίηση τιμής: %s', $card->name),
            'eyebrow' => 'Ειδοποίηση τιμής Cardora PRO',
            'title' => $isRise ? sprintf('Η τιμή της %s ανεβαίνει', $card->name) : sprintf('Η %s έγινε φθηνότερη', $card->name),
            'body' => sprintf('Η τιμή για την κάρτα %s στην Cardora άλλαξε σε %s.', $card->name, $priceText),
            'footer' => 'Λαμβάνεις αυτή την ειδοποίηση Cardora PRO επειδή έχεις ή παρακολουθείς αυτή την κάρτα/σετ.',
        ];
    }
}
