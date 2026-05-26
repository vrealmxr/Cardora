<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        MarketplaceNotificationService $notifications
    )
    {
        $validated = $request->validated();
        $baseHandle = $validated['handle'] ?? Str::slug($validated['display_name'] ?? $validated['name']);
        $baseHandle = $baseHandle !== '' ? $baseHandle : 'collector';
        $handle = $baseHandle;
        $suffix = 1;

        while (User::query()->where('handle', $handle)->exists()) {
            $handle = sprintf('%s-%d', $baseHandle, $suffix);
            $suffix++;
        }

        $user = User::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'handle' => $handle,
            'email' => $validated['email'],
            'city' => $validated['city'],
            'bio' => $validated['collector_tagline'],
            'collector_tagline' => $validated['collector_tagline'],
            'favorite_categories' => $validated['favorite_categories'],
            'profile_visibility' => 'public',
            'password' => $validated['password'],
            'locale' => $validated['locale'] ?? config('app.locale'),
            'trust_status' => 'basic',
            'last_seen_at' => now(),
        ]);

        $verificationEmailSent = true;

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
            $verificationEmailSent = false;
        }

        $token = $user->issueSingleSessionToken($validated['device_name'] ?? 'cardora-web');

        $notifications->createForUser(
            $user->getKey(),
            'welcome',
            __('api.notifications.welcome_title'),
            __('api.notifications.welcome_body'),
            ['user_id' => $user->getKey()],
            'security'
        );

        return response()->json([
            'message' => __('api.auth.registered'),
            'verification_email_sent' => $verificationEmailSent,
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => new UserProfileResource($user->loadMissing('sellerPayoutAccount')),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('api.auth.invalid_credentials')],
            ]);
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        $token = $user->issueSingleSessionToken($validated['device_name'] ?? 'cardora-web');

        return response()->json([
            'message' => __('api.auth.logged_in'),
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => new UserProfileResource($user->load('sellerPayoutAccount')),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load([
            'listings.product.category',
            'favorites.listing.product.category',
            'favorites.listing.seller',
            'purchases',
            'sales',
            'sellerPayoutAccount',
        ]);

        return response()->json([
            'data' => new UserProfileResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => __('api.auth.logged_out'),
        ]);
    }
}
