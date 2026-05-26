<?php

namespace App\Http\Controllers\Api;

use App\Mail\MarketplaceEventMail;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationMessageRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageModerationService;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->getKey();

        $conversations = Conversation::query()
            ->with(['listing.product.category', 'buyer', 'seller', 'messages.sender'])
            ->where(function ($query) use ($userId) {
                $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
            })
            ->latest('last_message_at')
            ->paginate($request->integer('per_page', 20));

        return ConversationResource::collection($conversations);
    }

    public function storeConversation(
        StoreConversationRequest $request,
        MarketplaceNotificationService $notifications,
        MessageModerationService $moderationService
    ) {
        $buyer = $request->user();
        $listing = Listing::query()->with('seller')->findOrFail($request->integer('listing_id'));

        abort_unless(
            $listing->seller_id !== $buyer->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $conversation = Conversation::firstOrCreate(
            [
                'listing_id' => $listing->getKey(),
                'buyer_id' => $buyer->getKey(),
                'seller_id' => $listing->seller_id,
            ],
            [
                'status' => 'active',
                'last_message_at' => now(),
                'metadata' => $request->validated('metadata'),
            ]
        );

        if ($request->filled('initial_message')) {
            DB::transaction(function () use (
                $request,
                $buyer,
                $listing,
                $conversation,
                $notifications,
                $moderationService
            ): void {
                $message = $this->createModeratedMessage(
                    $conversation,
                    $buyer->getKey(),
                    $request->string('initial_message')->toString(),
                    [],
                    null,
                    $request->validated('metadata'),
                    $moderationService
                );

                $conversation->update(['last_message_at' => now()]);

                $notifications->createForUser(
                    $listing->seller_id,
                    'message_received',
                    __('api.notifications.message_received_title'),
                    __('api.notifications.message_received_body', [
                        'sender' => $buyer->display_name ?: $buyer->name,
                    ]),
                    [
                        'conversation_id' => $conversation->getKey(),
                        'listing_id' => $listing->getKey(),
                        'message_id' => $message->getKey(),
                    ],
                    'messages'
                );

                if ($listing->seller) {
                    $notifications->sendEmailIfAllowed(
                        $listing->seller,
                        new MarketplaceEventMail(
                            $listing->seller,
                            $this->messageMailContent(
                                $listing->seller->locale,
                                $buyer->display_name ?: $buyer->name,
                                $listing->title_snapshot ?: $listing->product?->title ?: 'Cardora listing'
                            )
                        ),
                        'messages'
                    );
                }
            });
        }

        return response()->json([
            'message' => __('api.messages.conversation_started'),
            'data' => new ConversationResource(
                $conversation->fresh()->load(['listing.product.category', 'buyer', 'seller', 'messages.sender'])
            ),
        ], 201);
    }

    public function store(
        StoreConversationMessageRequest $request,
        MarketplaceNotificationService $notifications,
        MessageModerationService $moderationService
    ) {
        $conversation = Conversation::query()
            ->with(['buyer', 'seller', 'listing'])
            ->findOrFail($request->integer('conversation_id'));

        $this->ensureParticipant($request, $conversation);

        $sender = $request->user();
        $message = DB::transaction(function () use (
            $request,
            $conversation,
            $sender,
            $notifications,
            $moderationService
        ) {
            $message = $this->createModeratedMessage(
                $conversation,
                $sender->getKey(),
                $request->string('body')->toString(),
                $request->validated('attachments'),
                $request->validated('offer_amount'),
                $request->validated('metadata'),
                $moderationService
            );

            $conversation->update(['last_message_at' => now()]);

            $recipientId = $sender->getKey() === $conversation->buyer_id
                ? $conversation->seller_id
                : $conversation->buyer_id;

            $notifications->createForUser(
                $recipientId,
                'message_received',
                __('api.notifications.message_received_title'),
                __('api.notifications.message_received_body', [
                    'sender' => $sender->display_name ?: $sender->name,
                ]),
                [
                    'conversation_id' => $conversation->getKey(),
                    'listing_id' => $conversation->listing_id,
                    'message_id' => $message->getKey(),
                ],
                'messages'
            );

            $recipient = $sender->getKey() === $conversation->buyer_id
                ? $conversation->seller
                : $conversation->buyer;

            if ($recipient) {
                $notifications->sendEmailIfAllowed(
                    $recipient,
                    new MarketplaceEventMail(
                        $recipient,
                        $this->messageMailContent(
                            $recipient->locale,
                            $sender->display_name ?: $sender->name,
                            $conversation->listing?->title_snapshot ?: $conversation->listing?->product?->title ?: 'Cardora listing'
                        )
                    ),
                    'messages'
                );
            }

            return $message;
        });

        return response()->json([
            'message' => __('api.messages.sent'),
            'data' => new MessageResource($message->load('sender')),
        ], 201);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->ensureParticipant($request, $conversation);
        $this->markIncomingMessagesAsRead($request, $conversation);

        return new ConversationResource(
            $conversation->fresh()->load(['listing.product.category', 'buyer', 'seller', 'messages.sender'])
        );
    }

    public function markConversationRead(Request $request, Conversation $conversation)
    {
        $this->ensureParticipant($request, $conversation);
        $this->markIncomingMessagesAsRead($request, $conversation);

        return response()->json([
            'message' => __('api.messages.read'),
        ]);
    }

    public function update(UpdateMessageRequest $request, Message $message)
    {
        $conversation = $message->conversation;
        $user = $request->user();
        $this->ensureParticipant($request, $conversation);
        $moderationService = app(MessageModerationService::class);

        $payload = $request->validated();

        if (array_key_exists('read', $payload) && $message->sender_id !== $user->getKey()) {
            $message->update(['read_at' => $payload['read'] ? now() : null]);
        }

        if ($message->sender_id === $user->getKey()) {
            $updatePayload = collect($payload)->except('read')->all();

            if (array_key_exists('body', $updatePayload)) {
                $moderation = $moderationService->moderate($updatePayload['body']);

                $updatePayload = array_merge($updatePayload, [
                    'body_masked' => $moderation['masked_body'],
                    'moderation_status' => $moderation['moderation_status'],
                    'moderation_flags' => $moderation['moderation_flags'],
                    'moderation_score' => $moderation['moderation_score'],
                    'requires_admin_review' => $moderation['requires_admin_review'],
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                ]);
            }

            $message->update($updatePayload);
        }

        return response()->json([
            'message' => __('api.messages.updated'),
            'data' => new MessageResource($message->fresh()->load('sender')),
        ]);
    }

    public function destroy(Request $request, Message $message)
    {
        abort_unless(
            $message->sender_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $message->delete();

        return response()->noContent();
    }

    protected function ensureParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            in_array($request->user()->getKey(), [$conversation->buyer_id, $conversation->seller_id], true),
            403,
            __('api.errors.forbidden')
        );
    }

    protected function markIncomingMessagesAsRead(Request $request, Conversation $conversation): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    protected function createModeratedMessage(
        Conversation $conversation,
        int $senderId,
        string $body,
        ?array $attachments,
        ?float $offerAmount,
        ?array $metadata,
        MessageModerationService $moderationService
    ): Message {
        $moderation = $moderationService->moderate($body);

        return Message::create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $senderId,
            'body' => $moderation['original_body'],
            'body_masked' => $moderation['masked_body'],
            'attachments' => $attachments,
            'offer_amount' => $offerAmount,
            'metadata' => $metadata,
            'moderation_status' => $moderation['moderation_status'],
            'moderation_flags' => $moderation['moderation_flags'],
            'moderation_score' => $moderation['moderation_score'],
            'requires_admin_review' => $moderation['requires_admin_review'],
        ]);
    }

    protected function messageMailContent(?string $locale, string $senderName, string $listingTitle): array
    {
        $isEnglish = $locale === 'en';
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

        if ($isEnglish) {
            return [
                'subject' => sprintf('New message from %s on Cardora', $senderName),
                'eyebrow' => 'Cardora messages',
                'title' => 'You have a new message',
                'body' => sprintf('%s sent you a new message about %s.', $senderName, $listingTitle),
                'details' => [
                    ['label' => 'Sender', 'value' => $senderName],
                    ['label' => 'Listing', 'value' => $listingTitle],
                ],
                'cta' => 'Open messages',
                'url' => $frontendUrl.'/minymata',
                'footer' => 'You are receiving this because message email notifications are enabled in your Cardora account.',
            ];
        }

        return [
            'subject' => sprintf('Νέο μήνυμα από τον %s στην Cardora', $senderName),
            'eyebrow' => 'Μηνύματα Cardora',
            'title' => 'Έχεις νέο μήνυμα',
            'body' => sprintf('Ο %s σου έστειλε νέο μήνυμα σχετικά με το %s.', $senderName, $listingTitle),
            'details' => [
                ['label' => 'Αποστολέας', 'value' => $senderName],
                ['label' => 'Αγγελία', 'value' => $listingTitle],
            ],
            'cta' => 'Άνοιγμα μηνυμάτων',
            'url' => $frontendUrl.'/minymata',
            'footer' => 'Λαμβάνεις αυτό το email επειδή οι ειδοποιήσεις μηνυμάτων μέσω email είναι ενεργές στον λογαριασμό σου.',
        ];
    }
}
