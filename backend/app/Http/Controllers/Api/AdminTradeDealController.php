<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveTradeDisputeRequest;
use App\Http\Resources\TradeDealResource;
use App\Models\TradeDeal;
use App\Services\TradeEscrowService;
use Illuminate\Http\Request;

class AdminTradeDealController extends Controller
{
    public function index(Request $request)
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

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

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $deals = $query
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 25));

        return TradeDealResource::collection($deals);
    }

    public function resolve(
        TradeDeal $tradeDeal,
        ResolveTradeDisputeRequest $request,
        TradeEscrowService $trades
    ) {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $resolvedDeal = $trades->resolveDispute(
            $tradeDeal,
            $request->user(),
            (int) $request->validated('winner_user_id'),
            $request->validated('resolution_notes')
        );

        return response()->json([
            'message' => 'Trade dispute resolved successfully.',
            'data' => new TradeDealResource($resolvedDeal),
        ]);
    }
}
