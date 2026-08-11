<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\TradeDeal;
use App\Models\TradeRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class TradeEscrowService
{
    protected ?StripeClient $client = null;

    public function __construct(
        protected StripeConnectService $connectService,
        protected MarketplaceNotificationService $notifications
    ) {
    }

    public function createTradeRequest(Listing $listing, User $requester, array $payload): TradeRequest
    {
        $listing->loadMissing(['category', 'seller']);
        $this->assertTradeListing($listing);

        if ((int) $listing->seller_id === (int) $requester->getKey()) {
            throw ValidationException::withMessages([
                'listing_id' => ['You cannot send a trade request to your own listing.'],
            ]);
        }

        $bundle = $this->resolveTradeBundles($listing, $requester, $payload);
        $ownerListingIds = $bundle['owner_listing_ids'];
        $proposerListingIds = $bundle['proposer_listing_ids'];

        $existingActiveDeal = TradeDeal::query()
            ->whereIn('listing_id', $ownerListingIds)
            ->whereIn('status', ['pending_funding', 'funded', 'settling', 'disputed'])
            ->exists();

        if ($existingActiveDeal) {
            throw ValidationException::withMessages([
                'listing_id' => ['This trade listing is already in progress with another deal.'],
            ]);
        }

        if ($proposerListingIds !== []) {
            $proposerListingInActiveDeal = TradeDeal::query()
                ->whereIn('listing_id', $proposerListingIds)
                ->whereIn('status', ['pending_funding', 'funded', 'settling', 'disputed'])
                ->exists();

            if ($proposerListingInActiveDeal) {
                throw ValidationException::withMessages([
                    'offered_listing_ids' => ['One of your selected trade cards is already reserved in another active trade deal.'],
                ]);
            }
        }

        $existingPending = TradeRequest::query()
            ->where('listing_id', $listing->getKey())
            ->where('requester_user_id', $requester->getKey())
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'listing_id' => ['You already have a pending trade request for this listing.'],
            ]);
        }

        $offeredValue = $bundle['proposer_value'] > 0
            ? $bundle['proposer_value']
            : round((float) ($payload['offered_value'] ?? 0), 2);

        $offeredTitle = trim((string) Arr::get($payload, 'offered_title', ''));
        if ($offeredTitle === '' && $bundle['proposer_bundle_snapshot'] !== []) {
            $firstCard = $bundle['proposer_bundle_snapshot'][0]['title'] ?? 'Trade card';
            $offeredTitle = count($bundle['proposer_bundle_snapshot']) > 1
                ? sprintf('%s + %d more', $firstCard, count($bundle['proposer_bundle_snapshot']) - 1)
                : $firstCard;
        }

        if ($offeredTitle === '') {
            $offeredTitle = 'Trade card';
        }

        $offeredMetadata = array_merge((array) Arr::get($payload, 'offered_metadata', []), [
            'bundle_mode' => $proposerListingIds !== [] || count($ownerListingIds) > 1,
            'offered_listing_ids' => $proposerListingIds,
            'target_listing_ids' => $ownerListingIds,
            'owner_bundle' => $bundle['owner_bundle_snapshot'],
            'proposer_bundle' => $bundle['proposer_bundle_snapshot'],
            'owner_bundle_total' => $bundle['owner_value'],
            'proposer_bundle_total' => $bundle['proposer_value'],
            'swap_pairs' => $bundle['swap_pairs'],
        ]);

        $tradeRequestPayload = [
            'listing_id' => $listing->getKey(),
            'requester_user_id' => $requester->getKey(),
            'listing_owner_user_id' => (int) $listing->seller_id,
            'status' => 'pending',
            'offered_title' => $offeredTitle,
            'offered_description' => Arr::get($payload, 'offered_description'),
            'offered_condition' => Arr::get($payload, 'offered_condition'),
            'offered_value' => $offeredValue,
            'offered_images' => Arr::get($payload, 'offered_images', []),
            'offered_metadata' => $offeredMetadata,
            'request_message' => Arr::get($payload, 'request_message'),
            'terms_accepted' => (bool) Arr::get($payload, 'terms_accepted', false),
            'expires_at' => now()->addDays(7),
        ];

        if ($this->tradeRequestHasColumn('offered_listing_ids')) {
            $tradeRequestPayload['offered_listing_ids'] = $proposerListingIds;
        }

        if ($this->tradeRequestHasColumn('target_listing_ids')) {
            $tradeRequestPayload['target_listing_ids'] = $ownerListingIds;
        }

        if ($this->tradeRequestHasColumn('swap_pairs')) {
            $tradeRequestPayload['swap_pairs'] = $bundle['swap_pairs'];
        }

        $tradeRequest = TradeRequest::create($tradeRequestPayload);

        $this->notifications->createForUser(
            (int) $listing->seller_id,
            'trade_request_received',
            'New trade request received',
            sprintf('%s sent a trade proposal for "%s".', $requester->display_name ?: $requester->name, $listing->title_snapshot ?: ($listing->product?->title ?? 'your listing')),
            ['trade_request_id' => $tradeRequest->getKey(), 'listing_id' => $listing->getKey()],
            'messages'
        );

        return $tradeRequest->load(['listing.product.category', 'requester', 'listingOwner', 'tradeDeal']);
    }

    public function acceptTradeRequest(TradeRequest $tradeRequest, User $owner): TradeDeal
    {
        return DB::transaction(function () use ($tradeRequest, $owner) {
            $lockedRequest = TradeRequest::query()
                ->with(['listing.category', 'listing.product', 'requester', 'listingOwner'])
                ->lockForUpdate()
                ->findOrFail($tradeRequest->getKey());

            if ((int) $lockedRequest->listing_owner_user_id !== (int) $owner->getKey()) {
                throw ValidationException::withMessages([
                    'trade_request' => ['Only the listing owner can accept this trade request.'],
                ]);
            }

            if ($lockedRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'trade_request' => ['This trade request is no longer pending.'],
                ]);
            }

            $listing = $lockedRequest->listing;
            if (! $listing) {
                throw ValidationException::withMessages([
                    'trade_request' => ['The listing for this request no longer exists.'],
                ]);
            }

            $ownerListingIds = $this->resolveTradeRequestListingIds(
                $lockedRequest,
                'target_listing_ids',
                'target_listing_ids',
                'owner_bundle'
            );
            if ($ownerListingIds === []) {
                $ownerListingIds = [(int) $listing->getKey()];
            }

            if (! in_array((int) $listing->getKey(), $ownerListingIds, true)) {
                $ownerListingIds[] = (int) $listing->getKey();
            }

            $ownerListings = Listing::query()
                ->with(['category', 'seller', 'product'])
                ->lockForUpdate()
                ->whereIn('id', $ownerListingIds)
                ->get();

            if ($ownerListings->count() !== count($ownerListingIds)) {
                throw ValidationException::withMessages([
                    'trade_request' => ['One or more owner trade listings are no longer available.'],
                ]);
            }

            foreach ($ownerListings as $ownerListing) {
                if ((int) $ownerListing->seller_id !== (int) $lockedRequest->listing_owner_user_id) {
                    throw ValidationException::withMessages([
                        'trade_request' => ['All target trade cards must belong to the same listing owner.'],
                    ]);
                }

                $this->assertTradeListing($ownerListing);
            }

            $proposerListingIds = $this->resolveTradeRequestListingIds(
                $lockedRequest,
                'offered_listing_ids',
                'offered_listing_ids',
                'proposer_bundle'
            );
            $proposerListings = collect();

            if ($proposerListingIds !== []) {
                $proposerListings = Listing::query()
                    ->with(['category', 'seller', 'product'])
                    ->lockForUpdate()
                    ->whereIn('id', $proposerListingIds)
                    ->get();

                if ($proposerListings->count() !== count($proposerListingIds)) {
                    throw ValidationException::withMessages([
                        'trade_request' => ['One or more offered trade cards are no longer available.'],
                    ]);
                }

                foreach ($proposerListings as $proposerListing) {
                    if ((int) $proposerListing->seller_id !== (int) $lockedRequest->requester_user_id) {
                        throw ValidationException::withMessages([
                            'trade_request' => ['All offered trade cards must belong to the requesting user.'],
                        ]);
                    }

                    $this->assertTradeListing($proposerListing);
                }
            }

            $existingActiveDeal = TradeDeal::query()
                ->whereIn('listing_id', $ownerListingIds)
                ->whereIn('status', ['pending_funding', 'funded', 'settling', 'disputed'])
                ->exists();

            if ($existingActiveDeal) {
                throw ValidationException::withMessages([
                    'trade_request' => ['Another trade deal is already active for one of the selected listing cards.'],
                ]);
            }

            if ($proposerListingIds !== []) {
                $activeProposerDeal = TradeDeal::query()
                    ->whereIn('listing_id', $proposerListingIds)
                    ->whereIn('status', ['pending_funding', 'funded', 'settling', 'disputed'])
                    ->exists();

                if ($activeProposerDeal) {
                    throw ValidationException::withMessages([
                        'trade_request' => ['One of the offered cards is already reserved in another trade.'],
                    ]);
                }
            }

            $ownerValue = round(
                (float) Arr::get($lockedRequest->offered_metadata, 'owner_bundle_total', $this->sumListingValues($ownerListings)),
                2
            );
            $proposerValue = round(
                (float) Arr::get(
                    $lockedRequest->offered_metadata,
                    'proposer_bundle_total',
                    $proposerListings->isNotEmpty()
                        ? $this->sumListingValues($proposerListings)
                        : $lockedRequest->offered_value
                ),
                2
            );

            if ($proposerValue <= 0 || $ownerValue <= 0) {
                throw ValidationException::withMessages([
                    'trade_request' => ['Trade card values must be greater than zero for both sides.'],
                ]);
            }

            $depositAmount = max($ownerValue, $proposerValue);

            $delta = $ownerValue - $proposerValue;
            $ownerGross = round($depositAmount + $delta, 2);
            $proposerGross = round($depositAmount - $delta, 2);
            $feeRate = (float) config('services.stripe.trade_fee_rate', 0.05);
            $feePerSide = round($depositAmount * $feeRate, 2);
            $ownerFee = min($feePerSide, $ownerGross);
            $proposerFee = min($feePerSide, $proposerGross);
            $ownerNet = round(max($ownerGross - $ownerFee, 0), 2);
            $proposerNet = round(max($proposerGross - $proposerFee, 0), 2);
            $platformFee = round($ownerFee + $proposerFee, 2);

            $deal = TradeDeal::create([
                'trade_request_id' => $lockedRequest->getKey(),
                'listing_id' => $listing->getKey(),
                'owner_user_id' => (int) $lockedRequest->listing_owner_user_id,
                'proposer_user_id' => (int) $lockedRequest->requester_user_id,
                'status' => 'pending_funding',
                'owner_declared_value' => $ownerValue,
                'proposer_declared_value' => $proposerValue,
                'deposit_amount' => $depositAmount,
                'fee_rate' => $feeRate,
                'owner_gross_amount' => $ownerGross,
                'proposer_gross_amount' => $proposerGross,
                'owner_fee_amount' => $ownerFee,
                'proposer_fee_amount' => $proposerFee,
                'owner_net_amount' => $ownerNet,
                'proposer_net_amount' => $proposerNet,
                'platform_fee_amount' => $platformFee,
                'currency' => 'EUR',
                'metadata' => [
                    'terms_snapshot' => [
                        'dual_release_required' => true,
                        'auto_release' => false,
                        'platform_fee_rate' => $feeRate,
                        'dispute_fee_waiver' => true,
                    ],
                    'listing_bundle' => [
                        'owner_listing_ids' => $ownerListingIds,
                        'proposer_listing_ids' => $proposerListingIds,
                        'owner_cards' => $ownerListings->map(fn (Listing $item) => $this->buildListingSnapshot($item))->values()->all(),
                        'proposer_cards' => $proposerListings->map(fn (Listing $item) => $this->buildListingSnapshot($item))->values()->all(),
                        'swap_pairs' => $this->resolveTradeRequestSwapPairs($lockedRequest, $proposerListingIds, $ownerListingIds),
                    ],
                ],
            ]);

            $lockedRequest->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            TradeRequest::query()
                ->whereIn('listing_id', $ownerListingIds)
                ->where('id', '!=', $lockedRequest->getKey())
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                ]);

            $reservedListingIds = array_values(array_unique([...$ownerListingIds, ...$proposerListingIds]));
            Listing::query()
                ->whereIn('id', $reservedListingIds)
                ->update([
                    'status' => 'trade_in_progress',
                    'availability' => 'reserved_trade',
                ]);

            $this->notifications->createForUser(
                (int) $lockedRequest->requester_user_id,
                'trade_request_accepted',
                'Trade request accepted',
                'Your trade request was accepted. Complete the Stripe deposit to activate the trade.',
                ['trade_deal_id' => $deal->getKey(), 'trade_request_id' => $lockedRequest->getKey()],
                'orders'
            );

            return $deal->load($this->dealRelations());
        });
    }

    public function rejectTradeRequest(TradeRequest $tradeRequest, User $owner): TradeRequest
    {
        return DB::transaction(function () use ($tradeRequest, $owner) {
            $lockedRequest = TradeRequest::query()
                ->lockForUpdate()
                ->findOrFail($tradeRequest->getKey());

            if ((int) $lockedRequest->listing_owner_user_id !== (int) $owner->getKey()) {
                throw ValidationException::withMessages([
                    'trade_request' => ['Only the listing owner can reject this trade request.'],
                ]);
            }

            if ($lockedRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'trade_request' => ['Only pending trade requests can be rejected.'],
                ]);
            }

            $lockedRequest->update([
                'status' => 'rejected',
                'rejected_at' => now(),
            ]);

            $this->notifications->createForUser(
                (int) $lockedRequest->requester_user_id,
                'trade_request_rejected',
                'Trade request rejected',
                'Your trade request was rejected by the listing owner.',
                ['trade_request_id' => $lockedRequest->getKey()],
                'messages'
            );

            return $lockedRequest->fresh(['listing.product.category', 'requester', 'listingOwner', 'tradeDeal']);
        });
    }

    public function cancelTradeRequest(TradeRequest $tradeRequest, User $requester): TradeRequest
    {
        return DB::transaction(function () use ($tradeRequest, $requester) {
            $lockedRequest = TradeRequest::query()
                ->lockForUpdate()
                ->findOrFail($tradeRequest->getKey());

            if ((int) $lockedRequest->requester_user_id !== (int) $requester->getKey()) {
                throw ValidationException::withMessages([
                    'trade_request' => ['Only the requester can cancel this trade request.'],
                ]);
            }

            if ($lockedRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'trade_request' => ['Only pending trade requests can be cancelled.'],
                ]);
            }

            $lockedRequest->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return $lockedRequest->fresh(['listing.product.category', 'requester', 'listingOwner', 'tradeDeal']);
        });
    }

    public function createCheckoutSessionForParticipant(TradeDeal $tradeDeal, User $user): array
    {
        $tradeDeal->loadMissing(['owner.sellerPayoutAccount', 'proposer.sellerPayoutAccount', 'listing.product']);

        $role = $tradeDeal->participantRole((int) $user->getKey());
        if (! $role) {
            throw ValidationException::withMessages([
                'trade_deal' => ['You are not a participant in this trade deal.'],
            ]);
        }

        if (in_array($tradeDeal->status, ['settled', 'resolved', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'trade_deal' => ['This trade deal is already finalized.'],
            ]);
        }

        if ($tradeDeal->status === 'disputed') {
            throw ValidationException::withMessages([
                'trade_deal' => ['This trade deal is in dispute and cannot accept new payments.'],
            ]);
        }

        $tradeDeal = $this->syncFundingState($tradeDeal);

        $this->assertPayoutReady($tradeDeal->owner);
        $this->assertPayoutReady($tradeDeal->proposer);

        $paidAtField = $role === 'owner' ? 'owner_paid_at' : 'proposer_paid_at';
        if ($tradeDeal->{$paidAtField}) {
            return [
                'already_paid' => true,
                'trade_deal_id' => $tradeDeal->getKey(),
                'participant_role' => $role,
            ];
        }

        $existingSessionField = $role === 'owner'
            ? 'owner_stripe_checkout_session_id'
            : 'proposer_stripe_checkout_session_id';

        $existingSessionId = (string) ($tradeDeal->{$existingSessionField} ?? '');
        if ($existingSessionId !== '') {
            try {
                $existing = $this->stripe()->checkout->sessions->retrieve($existingSessionId, ['expand' => ['payment_intent']]);
                $existingPaymentIntent = $existing->payment_intent ?? null;
                $existingPaymentIntentId = is_string($existingPaymentIntent)
                    ? $existingPaymentIntent
                    : (is_object($existingPaymentIntent) ? ($existingPaymentIntent->id ?? null) : null);
                $existingPaymentStatus = (string) ($existing->payment_status ?? '');
                $existingStatus = (string) ($existing->status ?? '');

                if ($this->isSuccessfulTradePaymentStatus($existingPaymentStatus) || $existingStatus === 'complete') {
                    $tradeDeal = $this->markParticipantPaidFromStripe($tradeDeal, $role, [
                        'stripe_checkout_session_id' => $existing->id ?? null,
                        'stripe_payment_intent_id' => $existingPaymentIntentId,
                        'payment_status' => $existingPaymentStatus !== '' ? $existingPaymentStatus : 'paid',
                    ]);

                    if ($existingPaymentIntentId) {
                        try {
                            $intent = $this->stripe()->paymentIntents->retrieve($existingPaymentIntentId, ['expand' => ['charges.data']]);
                            $charge = $intent->charges->data[0] ?? null;
                            $chargeId = $charge->id ?? ($intent->latest_charge ?? null);

                            $tradeDeal = $this->markParticipantPaidFromStripe($tradeDeal, $role, [
                                'stripe_payment_intent_id' => $intent->id ?? null,
                                'stripe_charge_id' => is_string($chargeId) ? $chargeId : null,
                                'payment_status' => $intent->status ?? $existingPaymentStatus,
                            ]);
                        } catch (ApiErrorException $exception) {
                            Log::warning('Could not enrich trade payment from payment intent while reusing checkout session.', [
                                'trade_deal_id' => $tradeDeal->getKey(),
                                'role' => $role,
                                'payment_intent_id' => $existingPaymentIntentId,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }
                }

                $tradeDeal = $tradeDeal->fresh();
                if ($tradeDeal?->{$paidAtField}) {
                    return [
                        'already_paid' => true,
                        'trade_deal_id' => $tradeDeal->getKey(),
                        'participant_role' => $role,
                    ];
                }

                if ($existingStatus === 'open' && ! empty($existing->url)) {
                    return [
                        'checkout_session_id' => $existing->id,
                        'checkout_url' => $existing->url,
                        'expires_at' => $existing->expires_at,
                        'trade_deal_id' => $tradeDeal->getKey(),
                        'participant_role' => $role,
                    ];
                }
            } catch (ApiErrorException $exception) {
                Log::warning('Could not reuse trade checkout session.', [
                    'trade_deal_id' => $tradeDeal->getKey(),
                    'role' => $role,
                    'checkout_session_id' => $existingSessionId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $metadata = [
            'type' => 'trade_deposit',
            'trade_deal_id' => (string) $tradeDeal->getKey(),
            'participant_role' => $role,
            'participant_user_id' => (string) $user->getKey(),
        ];

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $this->tradeSuccessUrl($tradeDeal),
            'cancel_url' => $this->tradeCancelUrl($tradeDeal),
            'customer_email' => $user->email,
            'client_reference_id' => sprintf('trade_%s_%s', $tradeDeal->getKey(), $role),
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower((string) $tradeDeal->currency),
                    'product_data' => [
                        'name' => 'Cardora Trade Deposit',
                        'description' => sprintf('Security deposit for trade deal #%d (%s).', $tradeDeal->getKey(), $role),
                        'metadata' => $metadata,
                    ],
                    'unit_amount' => $this->toStripeAmount((float) $tradeDeal->deposit_amount),
                ],
                'quantity' => 1,
            ]],
            'metadata' => $metadata,
            'payment_intent_data' => [
                'metadata' => $metadata,
                'transfer_group' => $this->transferGroup($tradeDeal),
            ],
        ]);

        $tradeDeal->forceFill([
            $existingSessionField => $session->id,
        ])->save();

        return [
            'checkout_session_id' => $session->id,
            'checkout_url' => $session->url,
            'expires_at' => $session->expires_at,
            'trade_deal_id' => $tradeDeal->getKey(),
            'participant_role' => $role,
        ];
    }

    public function handleCheckoutSessionCompleted(object $session): ?TradeDeal
    {
        if (($session->metadata->type ?? null) !== 'trade_deposit') {
            return null;
        }

        $tradeDealId = isset($session->metadata->trade_deal_id)
            ? (int) $session->metadata->trade_deal_id
            : null;
        $role = (string) ($session->metadata->participant_role ?? '');

        if (! $tradeDealId || ! in_array($role, ['owner', 'proposer'], true)) {
            return null;
        }

        $deal = TradeDeal::query()->find($tradeDealId);
        if (! $deal) {
            return null;
        }

        return $this->markParticipantPaidFromStripe($deal, $role, [
            'stripe_checkout_session_id' => $session->id ?? null,
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
            'payment_status' => $session->payment_status ?? null,
        ]);
    }

    public function handlePaymentIntentSucceeded(object $paymentIntent): ?TradeDeal
    {
        if (($paymentIntent->metadata->type ?? null) !== 'trade_deposit') {
            return null;
        }

        $tradeDealId = isset($paymentIntent->metadata->trade_deal_id)
            ? (int) $paymentIntent->metadata->trade_deal_id
            : null;
        $role = (string) ($paymentIntent->metadata->participant_role ?? '');

        if (! $tradeDealId || ! in_array($role, ['owner', 'proposer'], true)) {
            return null;
        }

        $deal = TradeDeal::query()->find($tradeDealId);
        if (! $deal) {
            return null;
        }

        $charge = $paymentIntent->charges->data[0] ?? null;
        $chargeId = $charge->id ?? ($paymentIntent->latest_charge ?? null);

        return $this->markParticipantPaidFromStripe($deal, $role, [
            'stripe_payment_intent_id' => $paymentIntent->id ?? null,
            'stripe_charge_id' => is_string($chargeId) ? $chargeId : null,
            'payment_status' => $paymentIntent->status ?? null,
        ]);
    }

    public function confirmParticipantRelease(TradeDeal $tradeDeal, User $user): TradeDeal
    {
        $role = $tradeDeal->participantRole((int) $user->getKey());
        if (! $role) {
            throw ValidationException::withMessages([
                'trade_deal' => ['You are not a participant in this trade deal.'],
            ]);
        }

        $tradeDeal = $this->syncFundingState($tradeDeal);

        $shouldSettle = false;

        DB::transaction(function () use ($tradeDeal, $role, &$shouldSettle): void {
            $lockedDeal = TradeDeal::query()->lockForUpdate()->findOrFail($tradeDeal->getKey());

            if (! in_array($lockedDeal->status, ['funded'], true)) {
                throw ValidationException::withMessages([
                    'trade_deal' => ['Trade release is available only after both deposits are funded.'],
                ]);
            }

            $releaseField = $role === 'owner' ? 'owner_released_at' : 'proposer_released_at';

            if (! $lockedDeal->{$releaseField}) {
                $lockedDeal->forceFill([$releaseField => now()])->save();
            }

            if ($lockedDeal->fresh()->bothReleased()) {
                $lockedDeal->forceFill(['status' => 'settling'])->save();
                $shouldSettle = true;
            }
        });

        if (! $shouldSettle) {
            return $tradeDeal->fresh($this->dealRelations());
        }

        try {
            return $this->settleSuccessfulTrade($tradeDeal->fresh($this->dealRelations()));
        } catch (\Throwable $exception) {
            report($exception);

            TradeDeal::query()->whereKey($tradeDeal->getKey())->update([
                'status' => 'disputed',
                'disputed_at' => now(),
                'metadata' => array_merge($tradeDeal->metadata ?? [], [
                    'auto_settlement_failed' => [
                        'message' => $exception->getMessage(),
                        'at' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            throw ValidationException::withMessages([
                'trade_deal' => ['Automatic settlement failed. The trade was moved to dispute for manual admin resolution.'],
            ]);
        }
    }

    public function openDispute(TradeDeal $tradeDeal, User $user, string $reason): TradeDeal
    {
        $role = $tradeDeal->participantRole((int) $user->getKey());
        if (! $role) {
            throw ValidationException::withMessages([
                'trade_deal' => ['You are not a participant in this trade deal.'],
            ]);
        }

        if (! in_array($tradeDeal->status, ['funded', 'settling'], true)) {
            throw ValidationException::withMessages([
                'trade_deal' => ['This trade cannot be disputed in its current state.'],
            ]);
        }

        $tradeDeal->update([
            'status' => 'disputed',
            'disputed_at' => now(),
            'dispute_opened_by_user_id' => $user->getKey(),
            'metadata' => array_merge($tradeDeal->metadata ?? [], [
                'dispute' => [
                    'opened_by' => $user->getKey(),
                    'reason' => $reason,
                    'opened_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        return $tradeDeal->fresh($this->dealRelations());
    }

    public function resolveDispute(TradeDeal $tradeDeal, User $admin, int $winnerUserId, ?string $resolutionNotes = null): TradeDeal
    {
        if (! $admin->is_admin) {
            throw ValidationException::withMessages([
                'trade_deal' => ['Only admins can resolve trade disputes.'],
            ]);
        }

        $tradeDeal->refresh();

        if ($tradeDeal->status !== 'disputed') {
            throw ValidationException::withMessages([
                'trade_deal' => ['Only disputed trades can be resolved manually.'],
            ]);
        }

        if (! $tradeDeal->isParticipant($winnerUserId)) {
            throw ValidationException::withMessages([
                'winner_user_id' => ['The winner must be one of the trade participants.'],
            ]);
        }

        $tradeDeal->update(['status' => 'settling']);

        $deposit = round((float) $tradeDeal->deposit_amount, 2);
        $pool = round($deposit * 2, 2);

        $ownerTarget = (int) $tradeDeal->owner_user_id === $winnerUserId ? $pool : 0.0;
        $proposerTarget = (int) $tradeDeal->proposer_user_id === $winnerUserId ? $pool : 0.0;

        $settlementPayload = $this->executeStripeSettlement($tradeDeal->fresh($this->dealRelations()), $ownerTarget, $proposerTarget, 'dispute_resolution');

        $tradeDeal->refresh();
        $tradeDeal->update([
            'status' => 'resolved',
            'winner_user_id' => $winnerUserId,
            'resolution' => 'winner_take_all',
            'resolution_notes' => $resolutionNotes,
            'resolved_at' => now(),
            'owner_fee_amount' => 0,
            'proposer_fee_amount' => 0,
            'platform_fee_amount' => 0,
            'owner_net_amount' => $ownerTarget,
            'proposer_net_amount' => $proposerTarget,
            'payout_metadata' => $settlementPayload,
        ]);

        $this->completeListingAfterTrade($this->dealListingIds($tradeDeal));

        return $tradeDeal->fresh($this->dealRelations());
    }

    protected function settleSuccessfulTrade(TradeDeal $tradeDeal): TradeDeal
    {
        $tradeDeal->refresh();

        if ($tradeDeal->status === 'settled') {
            return $tradeDeal->fresh($this->dealRelations());
        }

        if ($tradeDeal->status !== 'settling') {
            throw ValidationException::withMessages([
                'trade_deal' => ['Trade is not in a settle-ready state.'],
            ]);
        }

        $ownerTarget = round((float) $tradeDeal->owner_net_amount, 2);
        $proposerTarget = round((float) $tradeDeal->proposer_net_amount, 2);

        $settlementPayload = $this->executeStripeSettlement($tradeDeal, $ownerTarget, $proposerTarget, 'dual_release');

        $tradeDeal->refresh();
        $tradeDeal->update([
            'status' => 'settled',
            'settled_at' => now(),
            'resolution' => 'dual_release',
            'payout_metadata' => $settlementPayload,
        ]);

        $this->completeListingAfterTrade($this->dealListingIds($tradeDeal));

        $this->notifications->createForUsers(
            [(int) $tradeDeal->owner_user_id, (int) $tradeDeal->proposer_user_id],
            'trade_settled',
            'Trade settled successfully',
            'Both releases were confirmed and the settlement was completed through Stripe.',
            ['trade_deal_id' => $tradeDeal->getKey()],
            'orders'
        );

        return $tradeDeal->fresh($this->dealRelations());
    }

    protected function executeStripeSettlement(
        TradeDeal $tradeDeal,
        float $ownerTarget,
        float $proposerTarget,
        string $reason
    ): array {
        $tradeDeal->loadMissing(['owner.sellerPayoutAccount', 'proposer.sellerPayoutAccount']);

        $deposit = round((float) $tradeDeal->deposit_amount, 2);
        $ownerChargeId = (string) ($tradeDeal->owner_stripe_charge_id ?? '');
        $proposerChargeId = (string) ($tradeDeal->proposer_stripe_charge_id ?? '');

        if ($ownerChargeId === '' || $proposerChargeId === '') {
            throw ValidationException::withMessages([
                'trade_deal' => ['Stripe payment references are incomplete for settlement.'],
            ]);
        }

        $ownerTarget = round(max($ownerTarget, 0), 2);
        $proposerTarget = round(max($proposerTarget, 0), 2);

        $ownerRefundAmount = min($deposit, $ownerTarget);
        $proposerRefundAmount = min($deposit, $proposerTarget);
        $ownerExtraAmount = round(max($ownerTarget - $ownerRefundAmount, 0), 2);
        $proposerExtraAmount = round(max($proposerTarget - $proposerRefundAmount, 0), 2);

        $refunds = [];
        $transfers = [];

        if ($ownerRefundAmount > 0) {
            $refunds['owner'] = $this->stripe()->refunds->create([
                'charge' => $ownerChargeId,
                'amount' => $this->toStripeAmount($ownerRefundAmount),
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'type' => 'trade_settlement_refund',
                    'trade_deal_id' => (string) $tradeDeal->getKey(),
                    'participant_role' => 'owner',
                    'settlement_reason' => $reason,
                ],
            ], [
                'idempotency_key' => sprintf('trade_deal_%s_owner_refund_%s', $tradeDeal->getKey(), $reason),
            ]);
        }

        if ($proposerRefundAmount > 0) {
            $refunds['proposer'] = $this->stripe()->refunds->create([
                'charge' => $proposerChargeId,
                'amount' => $this->toStripeAmount($proposerRefundAmount),
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'type' => 'trade_settlement_refund',
                    'trade_deal_id' => (string) $tradeDeal->getKey(),
                    'participant_role' => 'proposer',
                    'settlement_reason' => $reason,
                ],
            ], [
                'idempotency_key' => sprintf('trade_deal_%s_proposer_refund_%s', $tradeDeal->getKey(), $reason),
            ]);
        }

        if ($ownerExtraAmount > 0) {
            $ownerPayoutAccount = $this->assertPayoutReady($tradeDeal->owner);

            $transfers['owner'] = $this->stripe()->transfers->create([
                'amount' => $this->toStripeAmount($ownerExtraAmount),
                'currency' => strtolower((string) $tradeDeal->currency),
                'destination' => $ownerPayoutAccount->stripe_account_id,
                'source_transaction' => $proposerChargeId,
                'transfer_group' => $this->transferGroup($tradeDeal),
                'metadata' => [
                    'type' => 'trade_settlement_transfer',
                    'trade_deal_id' => (string) $tradeDeal->getKey(),
                    'participant_role' => 'owner',
                    'settlement_reason' => $reason,
                ],
            ], [
                'idempotency_key' => sprintf('trade_deal_%s_owner_transfer_%s', $tradeDeal->getKey(), $reason),
            ]);
        }

        if ($proposerExtraAmount > 0) {
            $proposerPayoutAccount = $this->assertPayoutReady($tradeDeal->proposer);

            $transfers['proposer'] = $this->stripe()->transfers->create([
                'amount' => $this->toStripeAmount($proposerExtraAmount),
                'currency' => strtolower((string) $tradeDeal->currency),
                'destination' => $proposerPayoutAccount->stripe_account_id,
                'source_transaction' => $ownerChargeId,
                'transfer_group' => $this->transferGroup($tradeDeal),
                'metadata' => [
                    'type' => 'trade_settlement_transfer',
                    'trade_deal_id' => (string) $tradeDeal->getKey(),
                    'participant_role' => 'proposer',
                    'settlement_reason' => $reason,
                ],
            ], [
                'idempotency_key' => sprintf('trade_deal_%s_proposer_transfer_%s', $tradeDeal->getKey(), $reason),
            ]);
        }

        return [
            'reason' => $reason,
            'targets' => [
                'owner' => $ownerTarget,
                'proposer' => $proposerTarget,
            ],
            'refunds' => [
                'owner' => [
                    'amount' => $ownerRefundAmount,
                    'stripe_refund_id' => $refunds['owner']->id ?? null,
                    'status' => $refunds['owner']->status ?? null,
                ],
                'proposer' => [
                    'amount' => $proposerRefundAmount,
                    'stripe_refund_id' => $refunds['proposer']->id ?? null,
                    'status' => $refunds['proposer']->status ?? null,
                ],
            ],
            'transfers' => [
                'owner' => [
                    'amount' => $ownerExtraAmount,
                    'stripe_transfer_id' => $transfers['owner']->id ?? null,
                ],
                'proposer' => [
                    'amount' => $proposerExtraAmount,
                    'stripe_transfer_id' => $transfers['proposer']->id ?? null,
                ],
            ],
            'completed_at' => now()->toIso8601String(),
        ];
    }

    protected function markParticipantPaidFromStripe(TradeDeal $tradeDeal, string $role, array $paymentPayload): TradeDeal
    {
        return DB::transaction(function () use ($tradeDeal, $role, $paymentPayload): TradeDeal {
            $lockedDeal = TradeDeal::query()->lockForUpdate()->findOrFail($tradeDeal->getKey());

            if (in_array($lockedDeal->status, ['settled', 'resolved', 'cancelled'], true)) {
                return $lockedDeal->fresh($this->dealRelations());
            }

            $sessionField = $role === 'owner'
                ? 'owner_stripe_checkout_session_id'
                : 'proposer_stripe_checkout_session_id';
            $intentField = $role === 'owner'
                ? 'owner_stripe_payment_intent_id'
                : 'proposer_stripe_payment_intent_id';
            $chargeField = $role === 'owner'
                ? 'owner_stripe_charge_id'
                : 'proposer_stripe_charge_id';
            $paidAtField = $role === 'owner' ? 'owner_paid_at' : 'proposer_paid_at';

            $updates = array_filter([
                $sessionField => Arr::get($paymentPayload, 'stripe_checkout_session_id'),
                $intentField => Arr::get($paymentPayload, 'stripe_payment_intent_id'),
                $chargeField => Arr::get($paymentPayload, 'stripe_charge_id'),
            ], fn ($value) => is_string($value) ? trim($value) !== '' : $value !== null);

            $paymentStatus = (string) Arr::get($paymentPayload, 'payment_status', '');
            if (in_array($paymentStatus, ['paid', 'succeeded'], true) && ! $lockedDeal->{$paidAtField}) {
                $updates[$paidAtField] = now();
            }

            if ($updates !== []) {
                $lockedDeal->forceFill($updates)->save();
            }

            $freshDeal = $lockedDeal->fresh();

            if ($freshDeal->bothPaid() && $freshDeal->status === 'pending_funding') {
                $freshDeal->update([
                    'status' => 'funded',
                    'funded_at' => now(),
                ]);

                $this->notifications->createForUsers(
                    [(int) $freshDeal->owner_user_id, (int) $freshDeal->proposer_user_id],
                    'trade_funded',
                    'Trade funded - ship your cards',
                    'Both deposits are now on hold. Ship your cards and release only after both sides receive successfully.',
                    ['trade_deal_id' => $freshDeal->getKey()],
                    'orders'
                );
            }

            return $freshDeal->fresh($this->dealRelations());
        });
    }

    public function syncFundingState(TradeDeal $tradeDeal): TradeDeal
    {
        $tradeDeal = $tradeDeal->fresh($this->dealRelations()) ?? $tradeDeal;

        if (! in_array((string) $tradeDeal->status, ['pending_funding', 'funded'], true)) {
            return $tradeDeal;
        }

        if ($tradeDeal->status === 'funded' || $tradeDeal->bothPaid()) {
            if ($tradeDeal->status === 'pending_funding' && $tradeDeal->bothPaid()) {
                $tradeDeal->update([
                    'status' => 'funded',
                    'funded_at' => $tradeDeal->funded_at ?? now(),
                ]);
            }

            return $tradeDeal->fresh($this->dealRelations());
        }

        $synced = $tradeDeal;
        $synced = $this->syncParticipantPaymentFromStripe($synced, 'owner');
        $synced = $this->syncParticipantPaymentFromStripe($synced, 'proposer');

        return $synced->fresh($this->dealRelations());
    }

    protected function syncParticipantPaymentFromStripe(TradeDeal $tradeDeal, string $role): TradeDeal
    {
        $paidAtField = $role === 'owner' ? 'owner_paid_at' : 'proposer_paid_at';
        if ($tradeDeal->{$paidAtField}) {
            return $tradeDeal;
        }

        $intentField = $role === 'owner'
            ? 'owner_stripe_payment_intent_id'
            : 'proposer_stripe_payment_intent_id';
        $sessionField = $role === 'owner'
            ? 'owner_stripe_checkout_session_id'
            : 'proposer_stripe_checkout_session_id';

        $intentId = (string) ($tradeDeal->{$intentField} ?? '');
        if ($intentId !== '') {
            try {
                $intent = $this->stripe()->paymentIntents->retrieve($intentId, ['expand' => ['charges.data']]);
                $charge = $intent->charges->data[0] ?? null;
                $chargeId = $charge->id ?? ($intent->latest_charge ?? null);

                if ($this->isSuccessfulTradePaymentStatus((string) ($intent->status ?? ''))) {
                    return $this->markParticipantPaidFromStripe($tradeDeal, $role, [
                        'stripe_payment_intent_id' => $intent->id ?? null,
                        'stripe_charge_id' => is_string($chargeId) ? $chargeId : null,
                        'payment_status' => $intent->status ?? null,
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::warning('Could not sync trade funding from payment intent.', [
                    'trade_deal_id' => $tradeDeal->getKey(),
                    'role' => $role,
                    'payment_intent_id' => $intentId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $sessionId = (string) ($tradeDeal->{$sessionField} ?? '');
        if ($sessionId === '') {
            return $tradeDeal;
        }

        try {
            $session = $this->stripe()->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent']]);
            $paymentIntent = $session->payment_intent ?? null;
            $paymentIntentId = is_string($paymentIntent)
                ? $paymentIntent
                : (is_object($paymentIntent) ? ($paymentIntent->id ?? null) : null);
            $sessionPaymentStatus = (string) ($session->payment_status ?? '');
            $sessionStatus = (string) ($session->status ?? '');

            if (! $this->isSuccessfulTradePaymentStatus($sessionPaymentStatus) && $sessionStatus !== 'complete') {
                return $tradeDeal;
            }

            $updated = $this->markParticipantPaidFromStripe($tradeDeal, $role, [
                'stripe_checkout_session_id' => $session->id ?? null,
                'stripe_payment_intent_id' => $paymentIntentId,
                'payment_status' => $sessionPaymentStatus !== '' ? $sessionPaymentStatus : 'paid',
            ]);

            if (! $paymentIntentId) {
                return $updated;
            }

            try {
                $intent = $this->stripe()->paymentIntents->retrieve($paymentIntentId, ['expand' => ['charges.data']]);
                $charge = $intent->charges->data[0] ?? null;
                $chargeId = $charge->id ?? ($intent->latest_charge ?? null);

                return $this->markParticipantPaidFromStripe($updated, $role, [
                    'stripe_payment_intent_id' => $intent->id ?? null,
                    'stripe_charge_id' => is_string($chargeId) ? $chargeId : null,
                    'payment_status' => $intent->status ?? $sessionPaymentStatus,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Could not enrich synced trade funding from payment intent.', [
                    'trade_deal_id' => $tradeDeal->getKey(),
                    'role' => $role,
                    'payment_intent_id' => $paymentIntentId,
                    'error' => $exception->getMessage(),
                ]);
            }

            return $updated;
        } catch (\Throwable $exception) {
            Log::warning('Could not sync trade funding from checkout session.', [
                'trade_deal_id' => $tradeDeal->getKey(),
                'role' => $role,
                'checkout_session_id' => $sessionId,
                'error' => $exception->getMessage(),
            ]);
        }

        return $tradeDeal;
    }

    protected function isSuccessfulTradePaymentStatus(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['paid', 'succeeded'], true);
    }

    protected function assertTradeListing(Listing $listing): void
    {
        $saleFormat = strtolower((string) ($listing->sale_format ?? 'fixed_price'));
        if ($saleFormat !== 'trade') {
            throw ValidationException::withMessages([
                'listing_id' => ['This listing is not available for trades.'],
            ]);
        }

        if (! in_array((string) $listing->status, ['active', 'published'], true)) {
            throw ValidationException::withMessages([
                'listing_id' => ['Only active trade listings can receive trade requests.'],
            ]);
        }

        if (! app(MarketplaceAccessService::class)->listingIsPubliclyVisible($listing->loadMissing('seller'))) {
            throw ValidationException::withMessages([
                'listing_id' => ['This listing is not currently visible for trade requests.'],
            ]);
        }

        $categorySlug = strtolower((string) ($listing->category?->slug ?? ''));
        $frontendKey = strtolower((string) data_get($listing->category?->metadata, 'frontend_key', ''));

        if (! in_array($categorySlug, ['kartes', 'cards'], true) && $frontendKey !== 'cards') {
            throw ValidationException::withMessages([
                'listing_id' => ['Trades are available only for card listings.'],
            ]);
        }
    }

    protected function resolveTradeBundles(Listing $anchorListing, User $requester, array $payload): array
    {
        $ownerListingIds = $this->normalizeListingIds(Arr::get($payload, 'target_listing_ids', []));
        if ($ownerListingIds === []) {
            $ownerListingIds = [(int) $anchorListing->getKey()];
        }

        if (! in_array((int) $anchorListing->getKey(), $ownerListingIds, true)) {
            $ownerListingIds[] = (int) $anchorListing->getKey();
        }

        $ownerListings = Listing::query()
            ->with(['category', 'seller', 'product'])
            ->whereIn('id', $ownerListingIds)
            ->get();

        if ($ownerListings->count() !== count($ownerListingIds)) {
            throw ValidationException::withMessages([
                'target_listing_ids' => ['One or more selected target cards are not available anymore.'],
            ]);
        }

        foreach ($ownerListings as $ownerListing) {
            if ((int) $ownerListing->seller_id !== (int) $anchorListing->seller_id) {
                throw ValidationException::withMessages([
                    'target_listing_ids' => ['All target cards must belong to the same user.'],
                ]);
            }

            $this->assertTradeListing($ownerListing);
        }

        $proposerListingIds = $this->normalizeListingIds(Arr::get($payload, 'offered_listing_ids', []));
        $proposerListings = collect();

        if ($proposerListingIds !== []) {
            $proposerListings = Listing::query()
                ->with(['category', 'seller', 'product'])
                ->whereIn('id', $proposerListingIds)
                ->get();

            if ($proposerListings->count() !== count($proposerListingIds)) {
                throw ValidationException::withMessages([
                    'offered_listing_ids' => ['One or more offered cards are not available anymore.'],
                ]);
            }

            foreach ($proposerListings as $proposerListing) {
                if ((int) $proposerListing->seller_id !== (int) $requester->getKey()) {
                    throw ValidationException::withMessages([
                        'offered_listing_ids' => ['You can offer only your own active trade cards.'],
                    ]);
                }

                $this->assertTradeListing($proposerListing);
            }
        }

        $swapPairs = collect(Arr::get($payload, 'swap_pairs', []))
            ->filter(fn ($pair) => is_array($pair))
            ->map(function (array $pair) use ($proposerListingIds, $ownerListingIds): ?array {
                $fromListingId = (int) ($pair['from_listing_id'] ?? 0);
                $toListingId = (int) ($pair['to_listing_id'] ?? 0);

                if ($fromListingId <= 0 || $toListingId <= 0) {
                    return null;
                }

                if (! in_array($fromListingId, $proposerListingIds, true) || ! in_array($toListingId, $ownerListingIds, true)) {
                    return null;
                }

                return [
                    'from_listing_id' => $fromListingId,
                    'to_listing_id' => $toListingId,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($swapPairs === [] && $proposerListingIds !== [] && $ownerListingIds !== []) {
            $maxIndex = min(count($proposerListingIds), count($ownerListingIds));
            $autoPairs = [];

            for ($index = 0; $index < $maxIndex; $index++) {
                $autoPairs[] = [
                    'from_listing_id' => (int) $proposerListingIds[$index],
                    'to_listing_id' => (int) $ownerListingIds[$index],
                ];
            }

            $swapPairs = $autoPairs;
        }

        return [
            'owner_listing_ids' => $ownerListingIds,
            'proposer_listing_ids' => $proposerListingIds,
            'owner_value' => $this->sumListingValues($ownerListings),
            'proposer_value' => $proposerListings->isNotEmpty() ? $this->sumListingValues($proposerListings) : 0.0,
            'owner_bundle_snapshot' => $ownerListings->map(fn (Listing $item) => $this->buildListingSnapshot($item))->values()->all(),
            'proposer_bundle_snapshot' => $proposerListings->map(fn (Listing $item) => $this->buildListingSnapshot($item))->values()->all(),
            'swap_pairs' => $swapPairs,
        ];
    }

    protected function normalizeListingIds(mixed $value): array
    {
        return collect((array) $value)
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveTradeRequestListingIds(
        TradeRequest $tradeRequest,
        string $columnKey,
        string $metadataKey,
        string $bundleKey
    ): array {
        $columnIds = $this->normalizeListingIds($tradeRequest->{$columnKey} ?? []);
        if ($columnIds !== []) {
            return $columnIds;
        }

        $metadata = (array) ($tradeRequest->offered_metadata ?? []);
        $metadataIds = $this->normalizeListingIds(Arr::get($metadata, $metadataKey, []));
        if ($metadataIds !== []) {
            return $metadataIds;
        }

        return collect((array) Arr::get($metadata, $bundleKey, []))
            ->map(fn ($card) => (int) Arr::get((array) $card, 'listing_id', 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveTradeRequestSwapPairs(
        TradeRequest $tradeRequest,
        array $proposerListingIds,
        array $ownerListingIds
    ): array {
        $metadata = (array) ($tradeRequest->offered_metadata ?? []);
        $rawPairs = $tradeRequest->swap_pairs;

        if (! is_array($rawPairs) || $rawPairs === []) {
            $rawPairs = Arr::get($metadata, 'swap_pairs', []);
        }

        $pairs = collect((array) $rawPairs)
            ->filter(fn ($pair) => is_array($pair))
            ->map(function (array $pair) use ($proposerListingIds, $ownerListingIds): ?array {
                $fromListingId = (int) ($pair['from_listing_id'] ?? 0);
                $toListingId = (int) ($pair['to_listing_id'] ?? 0);

                if ($fromListingId <= 0 || $toListingId <= 0) {
                    return null;
                }

                if (
                    ! in_array($fromListingId, $proposerListingIds, true) ||
                    ! in_array($toListingId, $ownerListingIds, true)
                ) {
                    return null;
                }

                return [
                    'from_listing_id' => $fromListingId,
                    'to_listing_id' => $toListingId,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($pairs === [] && $proposerListingIds !== [] && $ownerListingIds !== []) {
            $maxIndex = min(count($proposerListingIds), count($ownerListingIds));
            $autoPairs = [];

            for ($index = 0; $index < $maxIndex; $index++) {
                $autoPairs[] = [
                    'from_listing_id' => (int) $proposerListingIds[$index],
                    'to_listing_id' => (int) $ownerListingIds[$index],
                ];
            }

            $pairs = $autoPairs;
        }

        return $pairs;
    }

    protected function tradeRequestHasColumn(string $column): bool
    {
        static $resolved = [];

        if (array_key_exists($column, $resolved)) {
            return $resolved[$column];
        }

        try {
            $resolved[$column] = Schema::hasColumn('trade_requests', $column);
        } catch (\Throwable) {
            $resolved[$column] = false;
        }

        return $resolved[$column];
    }

    protected function sumListingValues($listings): float
    {
        return round(
            collect($listings)->reduce(
                fn (float $sum, Listing $listing) => $sum + (float) $listing->price,
                0.0
            ),
            2
        );
    }

    protected function buildListingSnapshot(Listing $listing): array
    {
        return [
            'listing_id' => (int) $listing->getKey(),
            'product_id' => (int) ($listing->product_id ?? 0),
            'title' => (string) ($listing->title_snapshot ?: ($listing->product?->title ?? 'Trade card')),
            'slug' => (string) ($listing->product?->slug ?? ''),
            'condition' => (string) ($listing->condition ?? ''),
            'rarity' => (string) ($listing->rarity ?? ''),
            'declared_value' => round((float) $listing->price, 2),
            'seller_user_id' => (int) $listing->seller_id,
            'seller_name' => (string) ($listing->seller?->display_name ?: $listing->seller?->name ?? ''),
            'media' => collect((array) ($listing->product?->media ?? []))
                ->take(3)
                ->values()
                ->all(),
        ];
    }

    protected function dealListingIds(TradeDeal $tradeDeal): array
    {
        $metadata = (array) ($tradeDeal->metadata ?? []);
        $bundle = (array) Arr::get($metadata, 'listing_bundle', []);
        $ownerListingIds = $this->normalizeListingIds(Arr::get($bundle, 'owner_listing_ids', []));
        $proposerListingIds = $this->normalizeListingIds(Arr::get($bundle, 'proposer_listing_ids', []));
        $primaryListingId = (int) ($tradeDeal->listing_id ?? 0);

        return collect([...$ownerListingIds, ...$proposerListingIds, $primaryListingId])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function assertPayoutReady(?User $user)
    {
        if (! $user) {
            throw ValidationException::withMessages([
                'trade_deal' => ['Trade participant account is missing.'],
            ]);
        }

        $account = $user->sellerPayoutAccount;

        if (! $account?->stripe_account_id) {
            throw ValidationException::withMessages([
                'trade_deal' => [sprintf('User %s must connect Stripe before joining this trade.', $user->display_name ?: $user->name)],
            ]);
        }

        $account = $this->connectService->syncLocalAccount($account);

        if (! $account->isFullyOnboarded()) {
            throw ValidationException::withMessages([
                'trade_deal' => [sprintf('User %s must complete Stripe onboarding before joining this trade.', $user->display_name ?: $user->name)],
            ]);
        }

        return $account;
    }

    protected function completeListingAfterTrade(int|array $listingIds): void
    {
        $ids = is_array($listingIds) ? $listingIds : [$listingIds];
        $ids = collect($ids)
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        Listing::query()->whereIn('id', $ids)->update([
            'status' => 'sold',
            'availability' => 'sold_out',
            'available_quantity' => 0,
        ]);
    }

    protected function dealRelations(): array
    {
        return [
            'tradeRequest.listing.product.category',
            'tradeRequest.requester',
            'tradeRequest.listingOwner',
            'listing.product.category',
            'owner',
            'proposer',
            'winner',
        ];
    }

    protected function transferGroup(TradeDeal $tradeDeal): string
    {
        return sprintf('trade_deal_%s', $tradeDeal->getKey());
    }

    protected function tradeSuccessUrl(TradeDeal $tradeDeal): string
    {
        $base = rtrim((string) config('services.stripe.trade_success_url', rtrim((string) config('services.frontend.url'), '/').'/paraggelies'), '/');

        return $this->tradeRedirectUrl($base, $tradeDeal, 'success');
    }

    protected function tradeCancelUrl(TradeDeal $tradeDeal): string
    {
        $base = rtrim((string) config('services.stripe.trade_cancel_url', rtrim((string) config('services.frontend.url'), '/').'/paraggelies'), '/');

        return $this->tradeRedirectUrl($base, $tradeDeal, 'cancelled');
    }

    protected function tradeRedirectUrl(string $base, TradeDeal $tradeDeal, string $paymentStatus): string
    {
        $query = http_build_query([
            'tab' => 'trades',
            'trade_deal' => (string) $tradeDeal->getKey(),
            'trade_payment' => $paymentStatus,
        ]);

        $separator = str_contains($base, '?') ? '&' : '?';

        return sprintf('%s%s%s', $base, $separator, $query);
    }

    protected function toStripeAmount(float $amount): int
    {
        return (int) round(max($amount, 0) * 100);
    }

    protected function stripe(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return $this->client = new StripeClient($secret);
    }
}
