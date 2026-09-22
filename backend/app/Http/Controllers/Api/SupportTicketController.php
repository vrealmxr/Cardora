<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Mail\MarketplaceEventMail;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = SupportTicket::query()
            ->with(['user', 'order'])
            ->where('user_id', $request->user()->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return SupportTicketResource::collection($tickets);
    }

    public function store(
        StoreSupportTicketRequest $request,
        MarketplaceNotificationService $notifications
    ) {
        $user = $request->user();
        $validated = $request->validated();
        $orderId = $validated['order_id'] ?? null;
        $isDsaNotice = ($validated['category'] ?? null) === 'dsa_notice';

        if ($orderId) {
            $order = Order::query()->findOrFail($orderId);
            abort_unless(
                in_array($user->getKey(), [$order->buyer_id, $order->seller_id], true),
                403,
                __('api.errors.forbidden')
            );
        }

        $ticket = SupportTicket::create([
            ...$validated,
            'user_id' => $user->getKey(),
            'status' => $validated['status'] ?? 'open',
            'priority' => $isDsaNotice ? 'high' : ($validated['priority'] ?? 'normal'),
        ]);

        if (
            isset($order)
            && in_array($validated['category'] ?? null, ['order_issue', 'dispute'], true)
            && $order->status === OrderStatus::PaidPendingRelease->value
        ) {
            $order->update([
                'status' => OrderStatus::Disputed->value,
                'escrow_status' => OrderStatus::Disputed->value,
                'disputed_at' => now(),
                'auto_release_at' => null,
                'metadata' => array_merge($order->metadata ?? [], [
                    'dispute_ticket_id' => $ticket->getKey(),
                    'dispute_ticket_category' => $validated['category'],
                ]),
            ]);
        }

        $notifications->createForUser(
            $user->getKey(),
            'support_created',
            __('api.notifications.support_created_title'),
            $isDsaNotice
                ? 'Η αναφορά σου καταχωρίστηκε και στάλθηκε στην ομάδα moderation της Cardora.'
                : __('api.notifications.support_created_body'),
            ['support_ticket_id' => $ticket->getKey()],
            'support'
        );

        $notifications->sendEmailIfAllowed(
            $user,
            new MarketplaceEventMail(
                $user,
                $this->supportMailContent(
                    $user->locale,
                    (string) $ticket->subject,
                    (string) $ticket->status,
                    $isDsaNotice
                )
            ),
            'support'
        );

        return response()->json([
            'message' => __('api.support.created'),
            'data' => new SupportTicketResource($ticket->load(['user', 'order'])),
        ], 201);
    }

    public function show(Request $request, SupportTicket $supportTicket)
    {
        $this->ensureOwnership($request, $supportTicket);

        return new SupportTicketResource($supportTicket->load(['user', 'order']));
    }

    public function update(Request $request, SupportTicket $supportTicket)
    {
        $this->ensureOwnership($request, $supportTicket);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        $supportTicket->update($validated);

        return response()->json([
            'message' => __('api.support.updated'),
            'data' => new SupportTicketResource($supportTicket->fresh()->load(['user', 'order'])),
        ]);
    }

    public function destroy(Request $request, SupportTicket $supportTicket)
    {
        $this->ensureOwnership($request, $supportTicket);

        $supportTicket->delete();

        return response()->noContent();
    }

    protected function ensureOwnership(Request $request, SupportTicket $supportTicket): void
    {
        abort_unless(
            $supportTicket->user_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );
    }

    protected function supportMailContent(?string $locale, string $subject, string $status, bool $isDsaNotice = false): array
    {
        $isEnglish = $locale === 'en';
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        if ($isEnglish) {
            return [
                'subject' => $isDsaNotice
                    ? 'Your Cardora DSA notice has been submitted'
                    : 'Your Cardora support ticket has been created',
                'eyebrow' => $isDsaNotice ? 'Cardora DSA notice' : 'Cardora support',
                'title' => $isDsaNotice ? 'Your notice was submitted' : 'Your support request is in',
                'body' => $isDsaNotice
                    ? 'Cardora received your notice and queued it for moderation review.'
                    : 'Cardora received your request and it is now visible inside your support center.',
                'details' => [
                    ['label' => 'Subject', 'value' => $subject],
                    ['label' => 'Status', 'value' => $status],
                ],
                'cta' => $isDsaNotice ? 'Open DSA page' : 'Open support center',
                'url' => $isDsaNotice ? $frontendUrl.'/dsa-notice-action' : $frontendUrl.'/kentro-ypostiriksis',
                'footer' => $isDsaNotice
                    ? 'You are receiving this because you submitted a notice to Cardora.'
                    : 'You are receiving this because support email notifications are enabled in your Cardora account.',
            ];
        }

        return [
            'subject' => $isDsaNotice
                ? 'Η αναφορά DSA καταχωρίστηκε στην Cardora'
                : 'Το αίτημα υποστήριξής σου καταχωρίστηκε στην Cardora',
            'eyebrow' => $isDsaNotice ? 'Cardora DSA notice' : 'Υποστήριξη Cardora',
            'title' => $isDsaNotice ? 'Η αναφορά σου καταχωρίστηκε' : 'Το αίτημά σου καταχωρίστηκε',
            'body' => $isDsaNotice
                ? 'Η Cardora έλαβε την αναφορά σου και την προώθησε σε moderation review με υψηλή προτεραιότητα.'
                : 'Η Cardora έλαβε το αίτημά σου και πλέον είναι ορατό μέσα στο support center σου.',
            'details' => [
                ['label' => 'Θέμα', 'value' => $subject],
                ['label' => 'Κατάσταση', 'value' => $status],
            ],
            'cta' => $isDsaNotice ? 'Άνοιγμα DSA σελίδας' : 'Άνοιγμα support center',
            'url' => $isDsaNotice ? $frontendUrl.'/dsa-notice-action' : $frontendUrl.'/kentro-ypostiriksis',
            'footer' => $isDsaNotice
                ? 'Λαμβάνεις αυτό το email επειδή υπέβαλες αναφορά προς την Cardora.'
                : 'Λαμβάνεις αυτό το email επειδή οι ειδοποιήσεις υποστήριξης μέσω email είναι ενεργές στον λογαριασμό σου.',
        ];
    }
}
