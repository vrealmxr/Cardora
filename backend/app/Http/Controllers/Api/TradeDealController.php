<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenTradeDisputeRequest;
use App\Http\Resources\TradeDealResource;
use App\Models\TradeDeal;
use App\Services\TradeEscrowService;
use Illuminate\Http\Request;

class TradeDealController extends Controller
{
    public function index(Request $request, TradeEscrowService $trades)
    {
        $user = $request->user();

        $query = TradeDeal::query()
            ->with([
                'tradeRequest.listing.product.category',
                'tradeRequest.requester',
                'tradeRequest.listingOwner',
                'listing.product.category',
                'owner',
                'proposer',
                'winner',
            ]);

        if (! $user->is_admin) {
            $query->where(function ($builder) use ($user): void {
                $builder
                    ->where('owner_user_id', $user->getKey())
                    ->orWhere('proposer_user_id', $user->getKey());
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('listing_id')) {
            $query->where('listing_id', $request->integer('listing_id'));
        }

        $deals = $query
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        $deals->getCollection()->transform(function (TradeDeal $deal) use ($trades) {
            return $trades->syncFundingState($deal);
        });

        return TradeDealResource::collection($deals);
    }

    public function show(TradeDeal $tradeDeal, Request $request, TradeEscrowService $trades)
    {
        $user = $request->user();

        abort_unless(
            $user->is_admin || $tradeDeal->isParticipant((int) $user->getKey()),
            403,
            'You are not allowed to view this trade deal.'
        );

        $tradeDeal = $trades->syncFundingState($tradeDeal);

        return new TradeDealResource($tradeDeal->load([
            'tradeRequest.listing.product.category',
            'tradeRequest.requester',
            'tradeRequest.listingOwner',
            'listing.product.category',
            'owner',
            'proposer',
            'winner',
        ]));
    }

    public function checkoutSession(TradeDeal $tradeDeal, Request $request, TradeEscrowService $trades)
    {
        $session = $trades->createCheckoutSessionForParticipant($tradeDeal, $request->user());

        return response()->json([
            'message' => 'Trade checkout session created.',
            'data' => $session,
        ]);
    }

    public function release(TradeDeal $tradeDeal, Request $request, TradeEscrowService $trades)
    {
        $updatedDeal = $trades->confirmParticipantRelease($tradeDeal, $request->user());

        return response()->json([
            'message' => 'Release confirmation saved.',
            'data' => new TradeDealResource($updatedDeal),
        ]);
    }

    public function dispute(
        TradeDeal $tradeDeal,
        OpenTradeDisputeRequest $request,
        TradeEscrowService $trades
    ) {
        $updatedDeal = $trades->openDispute(
            $tradeDeal,
            $request->user(),
            (string) $request->validated('reason')
        );

        return response()->json([
            'message' => 'Trade dispute opened. Cardora support will review this case manually.',
            'data' => new TradeDealResource($updatedDeal),
        ]);
    }
}
