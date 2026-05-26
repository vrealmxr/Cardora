<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFavoriteRequest;
use App\Http\Resources\FavoriteResource;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = Favorite::query()
            ->with(['listing.product.category', 'listing.seller'])
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->get();

        return FavoriteResource::collection($favorites);
    }

    public function store(StoreFavoriteRequest $request)
    {
        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->getKey(),
            'listing_id' => $request->integer('listing_id'),
        ]);

        return response()->json([
            'message' => __('api.favorites.added'),
            'data' => new FavoriteResource($favorite->load(['listing.product.category', 'listing.seller'])),
        ], 201);
    }

    public function show(Request $request, Favorite $favorite)
    {
        $this->ensureOwnership($request, $favorite);

        return new FavoriteResource($favorite->load(['listing.product.category', 'listing.seller']));
    }

    public function destroy(Request $request, Favorite $favorite)
    {
        $this->ensureOwnership($request, $favorite);

        $favorite->delete();

        return response()->json([
            'message' => __('api.favorites.removed'),
        ]);
    }

    protected function ensureOwnership(Request $request, Favorite $favorite): void
    {
        abort_unless(
            $favorite->user_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );
    }
}
