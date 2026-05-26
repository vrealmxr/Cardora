<?php

namespace App\Services;

use App\Filament\Resources\ListingResource;
use App\Filament\Resources\MessageResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\VerificationSubmissionResource;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payout;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VerificationSubmission;
use Illuminate\Support\Facades\Cache;

class AdminAttentionCenterService
{
    public function getDashboardPayload(): array
    {
        return Cache::remember('filament:admin-attention-center', now()->addSeconds(20), function (): array {
            $openVerificationStatuses = ['submitted', 'under_review', 'needs_revision'];
            $openSupportStatuses = ['open', 'investigating', 'waiting_on_user'];
            $pendingPayoutStatuses = ['pending', 'queued', 'processing'];

            $openVerificationSubmissions = VerificationSubmission::query()
                ->with(['user'])
                ->withCount('documents')
                ->whereIn('status', $openVerificationStatuses)
                ->latest('submitted_at')
                ->latest('created_at')
                ->get();

            $openVerificationUsers = $openVerificationSubmissions
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->count();

            $newListings = Listing::query()
                ->with(['product', 'seller'])
                ->latest('created_at')
                ->take(5)
                ->get();

            $newListingsTodayCount = Listing::query()
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $listingsPendingModerationCount = Listing::query()
                ->whereIn('status', ['pending_review', 'needs_revision'])
                ->count();

            $openSupportTickets = SupportTicket::query()
                ->with(['user', 'order'])
                ->whereIn('status', $openSupportStatuses)
                ->where('category', '!=', 'dsa_notice')
                ->latest('created_at')
                ->get();

            $openDsaNotices = SupportTicket::query()
                ->with(['user'])
                ->whereIn('status', $openSupportStatuses)
                ->where('category', 'dsa_notice')
                ->latest('created_at')
                ->get();

            $urgentSupportCount = $openSupportTickets
                ->whereIn('priority', ['high', 'urgent'])
                ->count();

            $disputedOrders = Order::query()
                ->with(['buyer', 'seller'])
                ->where('status', 'disputed')
                ->latest('disputed_at')
                ->latest('updated_at')
                ->get();

            $pendingPayouts = Payout::query()
                ->with(['user', 'order'])
                ->whereIn('status', $pendingPayoutStatuses)
                ->latest('initiated_at')
                ->latest('created_at')
                ->get();

            $pendingVerificationUsers = User::query()
                ->where('trust_status', 'reviewing')
                ->whereHas('verificationSubmissions', fn ($query) => $query->whereIn('status', $openVerificationStatuses))
                ->count();

            $flaggedMessages = Message::query()
                ->with(['sender', 'conversation.buyer', 'conversation.seller'])
                ->where('requires_admin_review', true)
                ->latest('created_at')
                ->get();

            $groups = [
                [
                    'key' => 'verifications',
                    'icon' => 'heroicon-o-shield-check',
                    'tone' => $openVerificationUsers > 0 ? 'danger' : 'success',
                    'label' => 'Verification queue',
                    'count' => $openVerificationUsers,
                    'description' => $openVerificationUsers > 0
                        ? sprintf(
                            '%d users are waiting for verification review across %d open submissions.',
                            $openVerificationUsers,
                            $openVerificationSubmissions->count()
                        )
                        : 'No members are currently waiting for identity, address or bank review.',
                    'meta' => sprintf('%d pending sellers in trust review', $pendingVerificationUsers),
                    'cta' => 'Open verification queue',
                    'url' => VerificationSubmissionResource::getUrl('index'),
                    'items' => $openVerificationSubmissions
                        ->unique('user_id')
                        ->take(5)
                        ->map(fn (VerificationSubmission $submission) => [
                            'title' => $this->userLabel($submission->user),
                            'subtitle' => sprintf(
                                '%s review · %d document(s)',
                                $this->verificationTypeLabel($submission->verification_type),
                                (int) ($submission->documents_count ?? 0)
                            ),
                            'time' => optional($submission->submitted_at ?: $submission->created_at)?->diffForHumans() ?? 'just now',
                            'url' => VerificationSubmissionResource::getUrl('edit', ['record' => $submission]),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'key' => 'listings',
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'tone' => $listingsPendingModerationCount > 0 ? 'warning' : 'info',
                    'label' => 'New listings',
                    'count' => $newListingsTodayCount,
                    'description' => $newListingsTodayCount > 0
                        ? sprintf(
                            '%d listing(s) were created in the last 24 hours. %d still need moderation.',
                            $newListingsTodayCount,
                            $listingsPendingModerationCount
                        )
                        : sprintf('%d listing(s) are still waiting in the moderation queue.', $listingsPendingModerationCount),
                    'meta' => sprintf('%d pending review right now', $listingsPendingModerationCount),
                    'cta' => 'Open listings',
                    'url' => ListingResource::getUrl('index'),
                    'items' => $newListings
                        ->map(fn (Listing $listing) => [
                            'title' => $listing->title_snapshot ?: $listing->product?->title ?: sprintf('Listing #%d', $listing->getKey()),
                            'subtitle' => sprintf(
                                '%s · %s',
                                $this->userLabel($listing->seller),
                                str_replace('_', ' ', $listing->status ?? 'draft')
                            ),
                            'time' => optional($listing->created_at)?->diffForHumans() ?? 'just now',
                            'url' => ListingResource::getUrl('edit', ['record' => $listing]),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'key' => 'message_safety',
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'tone' => $flaggedMessages->count() > 0 ? 'danger' : 'success',
                    'label' => 'Message safety',
                    'count' => $flaggedMessages->count(),
                    'description' => $flaggedMessages->count() > 0
                        ? sprintf(
                            '%d message(s) were flagged for contact details, abuse or attempts to move the deal outside Cardora.',
                            $flaggedMessages->count()
                        )
                        : 'No flagged marketplace conversations need review right now.',
                    'meta' => sprintf('%d pending moderation review', $flaggedMessages->count()),
                    'cta' => 'Open message watch',
                    'url' => MessageResource::getUrl('index'),
                    'items' => $flaggedMessages
                        ->take(5)
                        ->map(function (Message $message) {
                            $recipient = $message->conversation && $message->conversation->seller_id === $message->sender_id
                                ? $message->conversation->buyer
                                : $message->conversation?->seller;

                            return [
                                'title' => sprintf(
                                    '%s → %s',
                                    $this->userLabel($message->sender),
                                    $this->userLabel($recipient)
                                ),
                                'subtitle' => sprintf(
                                    '%s · %s',
                                    implode(', ', $message->moderation_flags ?? ['flagged']),
                                    str($message->body)->limit(90)->toString()
                                ),
                                'time' => optional($message->created_at)?->diffForHumans() ?? 'just now',
                                'url' => MessageResource::getUrl('edit', ['record' => $message]),
                            ];
                        })
                        ->values()
                        ->all(),
                ],
                [
                    'key' => 'dsa_notices',
                    'icon' => 'heroicon-o-exclamation-triangle',
                    'tone' => $openDsaNotices->count() > 0 ? 'danger' : 'success',
                    'label' => 'DSA notice queue',
                    'count' => $openDsaNotices->count(),
                    'description' => $openDsaNotices->count() > 0
                        ? sprintf('%d illegal-content notice(s) are waiting for moderation review.', $openDsaNotices->count())
                        : 'No DSA notices are waiting right now.',
                    'meta' => sprintf('%d open notice(s)', $openDsaNotices->count()),
                    'cta' => 'Open DSA queue',
                    'url' => SupportTicketResource::getUrl('index', ['tableFilters' => ['category' => ['value' => 'dsa_notice']]]),
                    'items' => $openDsaNotices
                        ->take(5)
                        ->map(fn (SupportTicket $ticket) => [
                            'title' => $ticket->subject,
                            'subtitle' => sprintf(
                                '%s · %s',
                                $this->userLabel($ticket->user),
                                data_get($ticket->metadata, 'notice_reason', 'notice submitted')
                            ),
                            'time' => optional($ticket->created_at)?->diffForHumans() ?? 'just now',
                            'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'key' => 'support',
                    'icon' => 'heroicon-o-lifebuoy',
                    'tone' => $urgentSupportCount > 0 ? 'danger' : ($openSupportTickets->count() > 0 ? 'warning' : 'success'),
                    'label' => 'Support tickets',
                    'count' => $openSupportTickets->count(),
                    'description' => $openSupportTickets->count() > 0
                        ? sprintf(
                            '%d support ticket(s) are open, including %d high or urgent conversations.',
                            $openSupportTickets->count(),
                            $urgentSupportCount
                        )
                        : 'No open support conversations need staff attention right now.',
                    'meta' => sprintf('%d high priority', $urgentSupportCount),
                    'cta' => 'Open support queue',
                    'url' => SupportTicketResource::getUrl('index'),
                    'items' => $openSupportTickets
                        ->take(5)
                        ->map(fn (SupportTicket $ticket) => [
                            'title' => $ticket->subject,
                            'subtitle' => sprintf(
                                '%s · %s priority',
                                $this->userLabel($ticket->user),
                                ucfirst((string) $ticket->priority)
                            ),
                            'time' => optional($ticket->created_at)?->diffForHumans() ?? 'just now',
                            'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'key' => 'operations',
                    'icon' => 'heroicon-o-banknotes',
                    'tone' => ($disputedOrders->count() + $pendingPayouts->count()) > 0 ? 'danger' : 'success',
                    'label' => 'Operations watch',
                    'count' => $disputedOrders->count() + $pendingPayouts->count(),
                    'description' => ($disputedOrders->count() + $pendingPayouts->count()) > 0
                        ? sprintf(
                            '%d disputed order(s) and %d pending payout(s) are waiting for operations follow-up.',
                            $disputedOrders->count(),
                            $pendingPayouts->count()
                        )
                        : 'No disputed orders or payout backlogs are waiting right now.',
                    'meta' => sprintf('%d disputes · %d payouts', $disputedOrders->count(), $pendingPayouts->count()),
                    'cta' => 'Open commerce ops',
                    'url' => $disputedOrders->count() > 0
                        ? OrderResource::getUrl('index')
                        : PayoutResource::getUrl('index'),
                    'items' => $this->buildOperationsItems($disputedOrders, $pendingPayouts),
                ],
            ];

            $urgentCount = collect($groups)->sum(fn (array $group) => (int) $group['count']);

            return [
                'headlineCount' => $urgentCount,
                'headline' => $urgentCount > 0
                    ? sprintf('%d admin item(s) need attention right now.', $urgentCount)
                    : 'Everything looks calm right now.',
                'subheadline' => 'Fresh verification requests, new listings, risky messages, DSA notices, support escalations and operations backlogs appear here first.',
                'hasUrgentItems' => $urgentCount > 0,
                'groups' => $groups,
            ];
        });
    }

    public function getDashboardNotifications(): array
    {
        $payload = $this->getDashboardPayload();
        $groups = collect($payload['groups']);

        return $groups
            ->filter(fn (array $group) => (int) $group['count'] > 0)
            ->take(4)
            ->map(fn (array $group) => [
                'title' => sprintf('%s: %d', $group['label'], (int) $group['count']),
                'body' => $group['description'],
                'tone' => $group['tone'],
                'url' => $group['url'],
                'action' => $group['cta'],
            ])
            ->values()
            ->all();
    }

    protected function buildOperationsItems($disputedOrders, $pendingPayouts): array
    {
        $orderItems = $disputedOrders
            ->take(3)
            ->map(fn (Order $order) => [
                'title' => sprintf('Disputed order %s', $order->order_number),
                'subtitle' => sprintf(
                    '%s vs %s',
                    $this->userLabel($order->buyer),
                    $this->userLabel($order->seller)
                ),
                'time' => optional($order->disputed_at ?: $order->updated_at)?->diffForHumans() ?? 'just now',
                'url' => OrderResource::getUrl('edit', ['record' => $order]),
            ]);

        $payoutItems = $pendingPayouts
            ->take(3)
            ->map(fn (Payout $payout) => [
                'title' => sprintf(
                    'Pending payout %s',
                    $payout->reference ?: sprintf('#%d', $payout->getKey())
                ),
                'subtitle' => sprintf(
                    '%s · %.2f EUR',
                    $this->userLabel($payout->user),
                    (float) $payout->amount
                ),
                'time' => optional($payout->initiated_at ?: $payout->created_at)?->diffForHumans() ?? 'just now',
                'url' => PayoutResource::getUrl('edit', ['record' => $payout]),
            ]);

        return $orderItems
            ->concat($payoutItems)
            ->take(5)
            ->values()
            ->all();
    }

    protected function userLabel(?User $user): string
    {
        if (! $user) {
            return 'Unknown user';
        }

        return $user->display_name ?: $user->handle ?: $user->email ?: sprintf('User #%d', $user->getKey());
    }

    protected function verificationTypeLabel(?string $value): string
    {
        return match ($value) {
            'identity' => 'Identity',
            'address' => 'Address',
            'bank' => 'Bank',
            default => ucfirst((string) $value),
        };
    }
}
