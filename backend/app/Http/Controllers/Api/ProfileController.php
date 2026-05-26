<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Services\UserNotificationPreferenceService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load([
            'listings.product.category',
            'favorites.listing.product.category',
            'favorites.listing.seller',
            'purchases.items.listing.product.category',
            'sales.items.listing.product.category',
            'collectionEntries.product.category',
            'profileLikesReceived',
            'sellerPayoutAccount',
        ]);

        return new UserProfileResource($user);
    }

    public function update(UpdateProfileRequest $request, UserNotificationPreferenceService $preferenceService)
    {
        $user = $request->user();
        $validated = $request->validated();

        if (array_key_exists('notification_preferences', $validated)) {
            $validated['notification_preferences'] = $preferenceService->normalize($validated['notification_preferences']);
        }

        $user->update($validated);

        return response()->json([
            'message' => __('api.profile.updated'),
            'data' => new UserProfileResource($user->fresh()),
        ]);
    }
}
