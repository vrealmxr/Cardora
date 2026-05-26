<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RespondToListingOfferRequest;
use App\Http\Requests\StoreListingOfferRequest;
use App\Models\Conversation;
use App\Models\ListingOffer;
use App\Services\ListingOfferService;
use Illuminate\Http\Request;

class ListingOfferController extends Controller
{
    public function store(
        StoreListingOfferRequest $request,
        Conversation $conversation,
        ListingOfferService $listingOffers
    ) {
        $this->ensureParticipant($request, $conversation);

        $offer = $listingOffers->createOffer(
            $conversation,
            $request->user(),
            (float) $request->validated('total_amount'),
            $request->validated('note'),
            app()->getLocale()
        );

        return response()->json([
            'message' => app()->getLocale() === 'en'
                ? 'Private offer sent.'
                : 'Η προσωπική προσφορά στάλθηκε.',
            'data' => [
                'id' => $offer->getKey(),
                'status' => $offer->status,
                'total_amount' => (float) $offer->total_amount,
            ],
        ], 201);
    }

    public function counter(
        RespondToListingOfferRequest $request,
        ListingOffer $listingOffer,
        ListingOfferService $listingOffers
    ) {
        $offer = $listingOffers->counterOffer(
            $listingOffer,
            $request->user(),
            (float) $request->validated('total_amount'),
            $request->validated('note'),
            app()->getLocale()
        );

        return response()->json([
            'message' => app()->getLocale() === 'en'
                ? 'Counteroffer sent.'
                : 'Η αντιπρόταση στάλθηκε.',
            'data' => [
                'id' => $offer->getKey(),
                'status' => $offer->status,
                'total_amount' => (float) $offer->total_amount,
            ],
        ]);
    }

    public function accept(
        RespondToListingOfferRequest $request,
        ListingOffer $listingOffer,
        ListingOfferService $listingOffers
    ) {
        $offer = $listingOffers->acceptOffer(
            $listingOffer,
            $request->user(),
            $request->validated('note'),
            app()->getLocale()
        );

        return response()->json([
            'message' => app()->getLocale() === 'en'
                ? 'Private offer accepted.'
                : 'Η προσωπική προσφορά έγινε αποδεκτή.',
            'data' => [
                'id' => $offer->getKey(),
                'status' => $offer->status,
                'total_amount' => (float) $offer->total_amount,
            ],
        ]);
    }

    public function reject(
        RespondToListingOfferRequest $request,
        ListingOffer $listingOffer,
        ListingOfferService $listingOffers
    ) {
        $offer = $listingOffers->rejectOffer(
            $listingOffer,
            $request->user(),
            $request->validated('note'),
            app()->getLocale()
        );

        return response()->json([
            'message' => app()->getLocale() === 'en'
                ? 'Private offer declined.'
                : 'Η προσωπική προσφορά απορρίφθηκε.',
            'data' => [
                'id' => $offer->getKey(),
                'status' => $offer->status,
                'total_amount' => (float) $offer->total_amount,
            ],
        ]);
    }

    protected function ensureParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            in_array((int) $request->user()->getKey(), [(int) $conversation->buyer_id, (int) $conversation->seller_id], true),
            403,
            __('api.errors.forbidden')
        );
    }
}
