<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTradeRequestRequest;
use App\Http\Resources\TradeRequestResource;
use App\Models\Listing;
use App\Models\TradeRequest;
use App\Services\TradeEscrowService;
use Illuminate\Http\Request;

class TradeRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $scope = (string) $request->query('scope', 'all');

        $query = TradeRequest::query()
            ->with(['listing.product.category', 'requester', 'listingOwner', 'tradeDeal']);

        if (! $user->is_admin) {
            $query->where(function ($builder) use ($user, $scope): void {
                if ($scope === 'sent') {
                    $builder->where('requester_user_id', $user->getKey());

                    return;
                }

                if ($scope === 'received') {
                    $builder->where('listing_owner_user_id', $user->getKey());

                    return;
                }

                $builder
                    ->where('requester_user_id', $user->getKey())
                    ->orWhere('listing_owner_user_id', $user->getKey());
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('listing_id')) {
            $query->where('listing_id', $request->integer('listing_id'));
        }

        $requests = $query
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return TradeRequestResource::collection($requests);
    }

    public function store(StoreTradeRequestRequest $request, TradeEscrowService $trades)
    {
        $listing = Listing::query()->with(['category', 'seller', 'product'])->findOrFail($request->integer('listing_id'));

        $tradeRequest = $trades->createTradeRequest($listing, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Trade request submitted successfully.',
            'data' => new TradeRequestResource($tradeRequest),
        ], 201);
    }

    public function accept(TradeRequest $tradeRequest, Request $request, TradeEscrowService $trades)
    {
        $deal = $trades->acceptTradeRequest($tradeRequest, $request->user());

        return response()->json([
            'message' => 'Trade request accepted. Both participants can now fund the trade deposit.',
            'data' => new \App\Http\Resources\TradeDealResource($deal),
        ]);
    }

    public function reject(TradeRequest $tradeRequest, Request $request, TradeEscrowService $trades)
    {
        $updatedRequest = $trades->rejectTradeRequest($tradeRequest, $request->user());

        return response()->json([
            'message' => 'Trade request rejected.',
            'data' => new TradeRequestResource($updatedRequest),
        ]);
    }

    public function cancel(TradeRequest $tradeRequest, Request $request, TradeEscrowService $trades)
    {
        $updatedRequest = $trades->cancelTradeRequest($tradeRequest, $request->user());

        return response()->json([
            'message' => 'Trade request cancelled.',
            'data' => new TradeRequestResource($updatedRequest),
        ]);
    }
}
