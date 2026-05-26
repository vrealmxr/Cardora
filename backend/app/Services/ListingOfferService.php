<?php

namespace App\Services;

use App\Mail\MarketplaceEventMail;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\ListingOffer;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Support\MarketplaceSellerFeeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingOfferService
{
    public const DEFAULT_SHIPPING_AMOUNT = 2.50;

    public function __construct(
        protected MarketplaceNotificationService $notifications,
        protected MessageModerationService $moderationService
    ) {
    }

    public function createOffer(
        Conversation $conversation,
        User $buyer,
        float $totalAmount,
        ?string $note,
        string $locale
    ): ListingOffer {
        $conversation->loadMissing(['listing.product', 'buyer', 'seller']);
        $listing = $conversation->listing;

        $this->assertBuyerCanCreateOffer($conversation, $buyer, $listing, $totalAmount);

        return DB::transaction(function () use ($conversation, $listing, $buyer, $totalAmount, $note, $locale) {
            $moderatedNote = $this->moderateOfferNote($note);

            $offer = ListingOffer::create([
                'listing_id' => $listing->getKey(),
                'conversation_id' => $conversation->getKey(),
                'buyer_id' => $conversation->buyer_id,
                'seller_id' => $conversation->seller_id,
                'initiator_id' => $buyer->getKey(),
                'recipient_id' => $conversation->seller_id,
                'status' => ListingOffer::STATUS_PENDING,
                'sequence' => $this->nextSequence($conversation),
                'item_amount' => $this->resolveItemAmount($listing, $totalAmount),
                'shipping_amount' => self::DEFAULT_SHIPPING_AMOUNT,
                'total_amount' => round($totalAmount, 2),
                'commission_amount' => $this->calculateCommission($totalAmount),
                'last_action_at' => now(),
                'metadata' => array_filter([
                    'source' => 'buyer_private_offer',
                    'note' => $moderatedNote,
                ]),
            ]);

            $this->createOfferMessage($conversation, $buyer, $offer, 'created', $moderatedNote, $locale);
            $conversation->update(['last_message_at' => now()]);
            $this->notifyOfferRecipient($offer, 'created', $locale);

            return $offer->fresh();
        });
    }

    public function counterOffer(
        ListingOffer $offer,
        User $actor,
        float $totalAmount,
        ?string $note,
        string $locale
    ): ListingOffer {
        $offer->loadMissing(['listing.product', 'conversation', 'buyer', 'seller', 'initiator', 'recipient']);
        $listing = $offer->listing;

        $this->assertCanRespond($offer, $actor, $listing);

        return DB::transaction(function () use ($offer, $listing, $actor, $totalAmount, $note, $locale) {
            $moderatedNote = $this->moderateOfferNote($note);

            $offer->update([
                'status' => ListingOffer::STATUS_COUNTERED,
                'last_action_at' => now(),
            ]);

            $counterOffer = ListingOffer::create([
                'listing_id' => $offer->listing_id,
                'conversation_id' => $offer->conversation_id,
                'buyer_id' => $offer->buyer_id,
                'seller_id' => $offer->seller_id,
                'initiator_id' => $actor->getKey(),
                'recipient_id' => $offer->initiator_id,
                'parent_offer_id' => $offer->getKey(),
                'status' => ListingOffer::STATUS_PENDING,
                'sequence' => $this->nextSequence($offer->conversation),
                'item_amount' => $this->resolveItemAmount($listing, $totalAmount),
                'shipping_amount' => self::DEFAULT_SHIPPING_AMOUNT,
                'total_amount' => round($totalAmount, 2),
                'commission_amount' => $this->calculateCommission($totalAmount),
                'last_action_at' => now(),
                'metadata' => array_filter([
                    'source' => 'counter_offer',
                    'note' => $moderatedNote,
                ]),
            ]);

            $this->createOfferMessage($offer->conversation, $actor, $counterOffer, 'countered', $moderatedNote, $locale);
            $offer->conversation->update(['last_message_at' => now()]);
            $this->notifyOfferRecipient($counterOffer, 'countered', $locale);

            return $counterOffer->fresh();
        });
    }

    public function acceptOffer(ListingOffer $offer, User $actor, ?string $note, string $locale): ListingOffer
    {
        $offer->loadMissing(['listing.product', 'conversation', 'buyer', 'seller', 'initiator', 'recipient']);
        $listing = $offer->listing;

        $this->assertCanRespond($offer, $actor, $listing);

        return DB::transaction(function () use ($offer, $actor, $note, $locale) {
            $moderatedNote = $this->moderateOfferNote($note);

            $offer->update([
                'status' => ListingOffer::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'last_action_at' => now(),
                'metadata' => $this->mergeOfferMetadata($offer->metadata, $moderatedNote),
            ]);

            $this->createOfferMessage($offer->conversation, $actor, $offer, 'accepted', $moderatedNote, $locale);
            $offer->conversation->update(['last_message_at' => now()]);
            $this->notifyOfferRecipient($offer, 'accepted', $locale);

            return $offer->fresh();
        });
    }

    public function rejectOffer(ListingOffer $offer, User $actor, ?string $note, string $locale): ListingOffer
    {
        $offer->loadMissing(['listing.product', 'conversation', 'buyer', 'seller', 'initiator', 'recipient']);
        $listing = $offer->listing;

        $this->assertCanRespond($offer, $actor, $listing);

        return DB::transaction(function () use ($offer, $actor, $note, $locale) {
            $moderatedNote = $this->moderateOfferNote($note);

            $offer->update([
                'status' => ListingOffer::STATUS_REJECTED,
                'rejected_at' => now(),
                'last_action_at' => now(),
                'metadata' => $this->mergeOfferMetadata($offer->metadata, $moderatedNote),
            ]);

            $this->createOfferMessage($offer->conversation, $actor, $offer, 'rejected', $moderatedNote, $locale);
            $offer->conversation->update(['last_message_at' => now()]);
            $this->notifyOfferRecipient($offer, 'rejected', $locale);

            return $offer->fresh();
        });
    }

    public function assertBuyerCanCheckout(ListingOffer $offer, User $buyer): void
    {
        $offer->loadMissing(['listing.product', 'conversation']);
        $listing = $offer->listing;

        if ((int) $offer->buyer_id !== (int) $buyer->getKey()) {
            $this->throwOfferError(__('Only the buyer can pay this private offer.'));
        }

        if (! $offer->isAccepted()) {
            $this->throwOfferError(__('This private offer is no longer ready for payment.'));
        }

        $this->assertListingEligible($listing);
        $this->assertListingAvailable($listing);
    }

    public function attachOrder(ListingOffer $offer, Order $order): void
    {
        $offer->update([
            'order_id' => $order->getKey(),
            'last_action_at' => now(),
        ]);
    }

    public function markPaid(ListingOffer $offer, Order $order): void
    {
        $offer->update([
            'order_id' => $order->getKey(),
            'status' => ListingOffer::STATUS_PAID,
            'paid_at' => now(),
            'last_action_at' => now(),
        ]);
    }

    protected function assertBuyerCanCreateOffer(
        Conversation $conversation,
        User $buyer,
        Listing $listing,
        float $totalAmount
    ): void {
        if ((int) $conversation->buyer_id !== (int) $buyer->getKey()) {
            $this->throwOfferError(__('Only the buyer can start a private offer.'));
        }

        $this->assertListingEligible($listing);
        $this->assertListingAvailable($listing);
        $this->assertMinimumOffer($listing, $totalAmount);

        if ((int) $listing->seller_id === (int) $buyer->getKey()) {
            $this->throwOfferError(__('You cannot send an offer to your own listing.'));
        }
    }

    protected function assertCanRespond(ListingOffer $offer, User $actor, Listing $listing): void
    {
        if (! $offer->isPending()) {
            $this->throwOfferError(__('This private offer is no longer awaiting a response.'));
        }

        if ((int) $offer->recipient_id !== (int) $actor->getKey()) {
            $this->throwOfferError(__('You cannot respond to this private offer.'));
        }

        $this->assertListingEligible($listing);
        $this->assertListingAvailable($listing);
    }

    protected function assertListingEligible(Listing $listing): void
    {
        if ($listing->sale_format !== 'fixed_price') {
            $this->throwOfferError(__('Private offers are available only on fixed-price listings.'));
        }

        if (! $listing->accept_offers) {
            $this->throwOfferError(__('This seller has not enabled private offers for the listing.'));
        }

        if (! in_array($listing->status, ['active', 'published'], true)) {
            $this->throwOfferError(__('This listing is no longer available for private offers.'));
        }
    }

    protected function assertListingAvailable(Listing $listing): void
    {
        $availableQuantity = (int) ($listing->available_quantity ?? $listing->quantity ?? 0);

        if ($availableQuantity < 1) {
            $this->throwOfferError(__('This listing is no longer available.'));
        }
    }

    protected function assertMinimumOffer(Listing $listing, float $totalAmount): void
    {
        $minimumOffer = (float) ($listing->minimum_offer ?? 0);

        if ($minimumOffer > 0 && round($totalAmount, 2) < round($minimumOffer, 2)) {
            $this->throwOfferError(
                __('The private offer must be at least :amount.', [
                    'amount' => number_format($minimumOffer, 2, '.', ''),
                ])
            );
        }
    }

    protected function resolveItemAmount(Listing $listing, float $totalAmount): float
    {
        $roundedTotal = round($totalAmount, 2);
        $itemAmount = round($roundedTotal - self::DEFAULT_SHIPPING_AMOUNT, 2);

        if ($itemAmount <= 0) {
            $this->throwOfferError(__('The agreed amount must stay above the included 2.50 shipping.'));
        }

        $this->assertMinimumOffer($listing, $roundedTotal);

        return $itemAmount;
    }

    protected function calculateCommission(float $totalAmount): float
    {
        $itemAmount = max(0, round($totalAmount, 2) - self::DEFAULT_SHIPPING_AMOUNT);

        return MarketplaceSellerFeeCalculator::calculate($itemAmount);
    }

    protected function nextSequence(Conversation $conversation): int
    {
        return ((int) $conversation->offers()->max('sequence')) + 1;
    }

    protected function createOfferMessage(
        Conversation $conversation,
        User $actor,
        ListingOffer $offer,
        string $action,
        ?array $moderatedNote,
        string $locale
    ): void {
        Message::create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $actor->getKey(),
            'body' => $this->offerMessageSummary($action, $locale),
            'body_masked' => null,
            'attachments' => [],
            'offer_amount' => (float) $offer->total_amount,
            'metadata' => array_filter([
                'message_type' => 'listing_offer',
                'listing_offer_id' => $offer->getKey(),
                'offer_action' => $action,
                'note' => $moderatedNote,
            ]),
            'moderation_status' => 'clean',
            'moderation_flags' => [],
            'moderation_score' => 0,
            'requires_admin_review' => false,
        ]);
    }

    protected function moderateOfferNote(?string $note): ?array
    {
        if ($note === null || trim($note) === '') {
            return null;
        }

        $moderated = $this->moderationService->moderate(trim($note));

        return [
            'body' => $moderated['masked_body'],
            'flags' => $moderated['moderation_flags'],
            'status' => $moderated['moderation_status'],
        ];
    }

    protected function mergeOfferMetadata(?array $metadata, ?array $moderatedNote): ?array
    {
        $metadata = is_array($metadata) ? $metadata : [];

        if ($moderatedNote !== null) {
            $metadata['note'] = $moderatedNote;
        }

        return $metadata === [] ? null : $metadata;
    }

    protected function offerMessageSummary(string $action, string $locale): string
    {
        $isEnglish = $locale === 'en';

        return match ($action) {
            'created' => $isEnglish ? 'Private offer sent.' : 'Στάλθηκε προσωπική προσφορά.',
            'countered' => $isEnglish ? 'Counteroffer sent.' : 'Στάλθηκε αντιπρόταση.',
            'accepted' => $isEnglish ? 'Private offer accepted.' : 'Η προσωπική προσφορά έγινε αποδεκτή.',
            'rejected' => $isEnglish ? 'Private offer declined.' : 'Η προσωπική προσφορά απορρίφθηκε.',
            default => $isEnglish ? 'Offer update.' : 'Ενημέρωση προσφοράς.',
        };
    }

    protected function notifyOfferRecipient(ListingOffer $offer, string $action, string $locale): void
    {
        $offer->loadMissing(['listing.product', 'buyer', 'seller', 'recipient']);

        $listingTitle = (string) ($offer->listing?->title_snapshot ?: $offer->listing?->product?->title ?: 'Cardora listing');
        $actorName = (int) $offer->initiator_id === (int) $offer->buyer_id
            ? ($offer->buyer?->display_name ?: $offer->buyer?->name ?: 'Buyer')
            : ($offer->seller?->display_name ?: $offer->seller?->name ?: 'Seller');

        $body = $locale === 'en'
            ? sprintf('%s updated a private offer for %s.', $actorName, $listingTitle)
            : sprintf('%s ενημέρωσε μια προσωπική προσφορά για το %s.', $actorName, $listingTitle);
        $title = $locale === 'en'
            ? 'Private offer update'
            : 'Ενημέρωση προσωπικής προσφοράς';

        $this->notifications->createForUser(
            (int) $offer->recipient_id,
            'listing_offer_update',
            $title,
            $body,
            [
                'conversation_id' => $offer->conversation_id,
                'listing_id' => $offer->listing_id,
                'listing_offer_id' => $offer->getKey(),
                'offer_action' => $action,
            ],
            'messages'
        );

        if ($offer->recipient) {
            $this->notifications->sendEmailIfAllowed(
                $offer->recipient,
                new MarketplaceEventMail(
                    $offer->recipient,
                    [
                        'subject' => $title,
                        'eyebrow' => 'Cardora offers',
                        'title' => $title,
                        'body' => $body,
                        'details' => [
                            ['label' => $locale === 'en' ? 'Listing' : 'Αγγελία', 'value' => $listingTitle],
                            ['label' => $locale === 'en' ? 'Total' : 'Σύνολο', 'value' => number_format((float) $offer->total_amount, 2, ',', '.').' EUR'],
                        ],
                        'cta' => $locale === 'en' ? 'Open messages' : 'Άνοιγμα μηνυμάτων',
                        'url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/').'/minymata?conversation='.$offer->conversation_id,
                        'footer' => $locale === 'en'
                            ? 'The agreed total already includes the 2.50 shipping amount.'
                            : 'Το συμφωνημένο ποσό περιλαμβάνει ήδη και τα 2,50€ των μεταφορικών.',
                    ]
                ),
                'messages'
            );
        }
    }

    protected function throwOfferError(string $message): never
    {
        throw ValidationException::withMessages([
            'offer' => [$message],
        ]);
    }
}
