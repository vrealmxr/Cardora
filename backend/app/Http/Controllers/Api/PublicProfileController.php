<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProfileResource;
use App\Models\ProfileLike;
use App\Models\UserFollow;
use App\Models\User;
use App\Services\MarketplaceAccessService;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicProfileController extends Controller
{
    public function show(Request $request, User $profile)
    {
        return new PublicProfileResource($this->loadPublicProfile($request, $profile));
    }

    public function like(
        Request $request,
        User $profile,
        MarketplaceNotificationService $notifications
    ) {
        if ($request->user()->getKey() === $profile->getKey()) {
            throw ValidationException::withMessages([
                'profile' => [__('api.profile.cannot_like_own_profile')],
            ]);
        }

        ProfileLike::firstOrCreate([
            'user_id' => $request->user()->getKey(),
            'profile_user_id' => $profile->getKey(),
        ]);

        $notifications->createForUser(
            $profile->getKey(),
            'profile_like',
            __('api.notifications.profile_liked_title'),
            __('api.notifications.profile_liked_body', [
                'user' => $request->user()->display_name ?: $request->user()->name,
            ]),
            ['profile_user_id' => $profile->getKey()]
        );

        return response()->json([
            'message' => __('api.profile.liked'),
            'data' => new PublicProfileResource($this->loadPublicProfile($request, $profile->fresh())),
        ]);
    }

    public function unlike(Request $request, User $profile)
    {
        ProfileLike::query()
            ->where('user_id', $request->user()->getKey())
            ->where('profile_user_id', $profile->getKey())
            ->delete();

        return response()->json([
            'message' => __('api.profile.unliked'),
            'data' => new PublicProfileResource($this->loadPublicProfile($request, $profile->fresh())),
        ]);
    }

    public function follow(
        Request $request,
        User $profile,
        MarketplaceNotificationService $notifications
    ) {
        if ($request->user()->getKey() === $profile->getKey()) {
            throw ValidationException::withMessages([
                'profile' => [__('api.profile.cannot_follow_own_profile')],
            ]);
        }

        UserFollow::firstOrCreate([
            'user_id' => $request->user()->getKey(),
            'followed_user_id' => $profile->getKey(),
        ]);

        $notifications->createForUser(
            $profile->getKey(),
            'profile_follow',
            __('api.notifications.profile_followed_title'),
            __('api.notifications.profile_followed_body', [
                'user' => $request->user()->display_name ?: $request->user()->name,
            ]),
            ['profile_user_id' => $profile->getKey()],
            'follows'
        );

        return response()->json([
            'message' => __('api.profile.followed'),
            'data' => new PublicProfileResource($this->loadPublicProfile($request, $profile->fresh())),
        ]);
    }

    public function unfollow(Request $request, User $profile)
    {
        UserFollow::query()
            ->where('user_id', $request->user()->getKey())
            ->where('followed_user_id', $profile->getKey())
            ->delete();

        return response()->json([
            'message' => __('api.profile.unfollowed'),
            'data' => new PublicProfileResource($this->loadPublicProfile($request, $profile->fresh())),
        ]);
    }

    protected function loadPublicProfile(Request $request, User $profile): User
    {
        abort_if($profile->profile_visibility === 'private', 404);
        $marketplaceAccess = app(MarketplaceAccessService::class);
        $sellerCanKeepListingsVisible = $marketplaceAccess->hasRequiredSellerShippingOrigin($profile);

        $profile->load([
            'collectionEntries' => fn ($query) => $query
                ->where('visibility', 'public')
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->with(['product.category']),
            'listings' => fn ($query) => $query
                ->whereIn('status', ['active', 'published'])
                ->latest()
                ->with(['product.category', 'category']),
            'receivedReviews' => fn ($query) => $query
                ->where('is_public', true)
                ->latest()
                ->limit(6)
                ->with(['reviewer', 'listing.product.category', 'product.category']),
        ])->loadCount([
            'profileLikesReceived as profile_likes_count',
            'profileFollowersReceived as followers_count',
            'profileFollowsGiven as following_count',
            'collectionEntries as collection_entries_count' => fn ($query) => $query->where('visibility', 'public'),
            'listings as active_listings_count' => fn ($query) => $query->whereIn('status', ['active', 'published']),
        ]);

        if (! $sellerCanKeepListingsVisible) {
            $profile->setRelation('listings', collect());
            $profile->setAttribute('active_listings_count', 0);
        }

        $profile->setAttribute(
            'liked_by_auth_user',
            $request->user()
                ? ProfileLike::query()
                    ->where('user_id', $request->user()->getKey())
                    ->where('profile_user_id', $profile->getKey())
                    ->exists()
                : false
        );

        $profile->setAttribute(
            'followed_by_auth_user',
            $request->user()
                ? UserFollow::query()
                    ->where('user_id', $request->user()->getKey())
                    ->where('followed_user_id', $profile->getKey())
                    ->exists()
                : false
        );

        return $profile;
    }
}
