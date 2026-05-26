<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuctionBidRequest;
use App\Http\Resources\AuctionBidResource;
use App\Models\AuctionBid;
use App\Models\Listing;
use App\Services\MarketplaceAccessService;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionBidController extends Controller
{
    public function index(Request $request, Listing $listing)
    {
        $bids = AuctionBid::query()
            ->with('bidder')
            ->where('listing_id', $listing->id)
            ->latest('placed_at')
            ->paginate($request->integer('per_page', 25));

        return AuctionBidResource::collection($bids);
    }

    public function store(
        StoreAuctionBidRequest $request,
        Listing $listing,
        MarketplaceAccessService $marketplaceAccess,
        MarketplaceNotificationService $notifications
    ) {
        $bidder = $request->user();
        $marketplaceAccess->assertCanBuy($bidder, app()->getLocale());

        if ($listing->sale_format !== 'auction') {
            throw ValidationException::withMessages([
                'listing' => [__('api.auctions.invalid_format')],
            ]);
        }

        $minimumAllowedBid = max(
            (float) ($listing->current_bid ?? 0) + (float) ($listing->bid_increment ?? 0),
            (float) ($listing->starting_bid ?? 0)
        );

        if ((float) $request->input('amount') < $minimumAllowedBid) {
            throw ValidationException::withMessages([
                'amount' => [__('api.auctions.bid_too_low', ['amount' => number_format($minimumAllowedBid, 2)])],
            ]);
        }

        $bid = DB::transaction(function () use ($request, $listing, $bidder) {
            $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());

            $bid = AuctionBid::create([
                'listing_id' => $lockedListing->getKey(),
                'bidder_id' => $bidder->getKey(),
                'amount' => $request->input('amount'),
                'placed_at' => $request->input('placed_at', now()),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'status' => $request->input('status', 'submitted'),
                'metadata' => $request->validated('metadata'),
            ]);

            $lockedListing->update([
                'current_bid' => $bid->amount,
                'winning_bidder_id' => $bidder->getKey(),
            ]);

            return $bid->load('bidder');
        });

        $notifications->createForUser(
            $listing->seller_id,
            'bid_received',
            __('api.notifications.bid_received_title'),
            __('api.notifications.bid_received_body', [
                'bidder' => $bidder->display_name ?: $bidder->name,
            ]),
            ['listing_id' => $listing->getKey(), 'bid_id' => $bid->getKey()],
            'orders'
        );

        return response()->json([
            'message' => __('api.auctions.bid_created'),
            'data' => new AuctionBidResource($bid),
        ], 201);
    }
}
