<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $clientId = trim((string) config('services.google.client_id'));
        $redirectUri = trim((string) config('services.google.redirect'));

        if ($clientId === '' || $redirectUri === '') {
            return redirect()->away($this->buildFrontendCallbackUrl([
                'status' => 'error',
                'message' => __('api.auth.google_not_configured'),
            ]));
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function callback(MarketplaceNotificationService $notifications): RedirectResponse
    {
        if (request()->has('error')) {
            return redirect()->away($this->buildFrontendCallbackUrl([
                'status' => 'error',
                'message' => __('api.auth.google_failed'),
            ]));
        }

        $code = trim((string) request()->query('code', ''));
        if ($code === '') {
            return redirect()->away($this->buildFrontendCallbackUrl([
                'status' => 'error',
                'message' => __('api.auth.google_failed'),
            ]));
        }

        try {
            $tokenPayload = $this->exchangeCodeForToken($code);
            $googleUser = $this->fetchGoogleUser($tokenPayload['access_token'] ?? '');
            [$token, $isNewUser] = $this->authenticateGoogleUser($googleUser, $notifications);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($this->buildFrontendCallbackUrl([
                'status' => 'error',
                'message' => __('api.auth.google_failed'),
            ]));
        }

        return redirect()->away($this->buildFrontendCallbackUrl([
            'status' => 'success',
            'token' => $token,
            'new_user' => $isNewUser ? '1' : '0',
        ]));
    }

    public function code(Request $request, MarketplaceNotificationService $notifications): JsonResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'string'],
        ]);

        try {
            $tokenPayload = $this->exchangeCodeForToken(
                trim((string) $payload['code']),
                'postmessage'
            );
            $googleUser = $this->fetchGoogleUser($tokenPayload['access_token'] ?? '');
            [$token, $isNewUser] = $this->authenticateGoogleUser($googleUser, $notifications);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('api.auth.google_failed'),
            ], 422);
        }

        return response()->json([
            'token' => $token,
            'new_user' => $isNewUser,
        ]);
    }

    protected function exchangeCodeForToken(string $code, ?string $redirectUriOverride = null): array
    {
        $clientId = trim((string) config('services.google.client_id'));
        $clientSecret = trim((string) config('services.google.client_secret'));
        $redirectUri = trim((string) ($redirectUriOverride ?? config('services.google.redirect')));

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new \RuntimeException('Google OAuth credentials are not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Google token exchange failed.');
        }

        return $response->json() ?? [];
    }

    protected function fetchGoogleUser(string $accessToken): array
    {
        if ($accessToken === '') {
            throw new \RuntimeException('Missing Google access token.');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch Google user profile.');
        }

        return $response->json() ?? [];
    }

    protected function authenticateGoogleUser(array $googleUser, MarketplaceNotificationService $notifications): array
    {
        $email = Str::lower(trim((string) ($googleUser['email'] ?? '')));
        if ($email === '') {
            throw new \RuntimeException(__('api.auth.google_missing_email'));
        }

        $name = trim((string) (($googleUser['name'] ?? '') ?: Str::before($email, '@')));
        $name = $name !== '' ? $name : 'Cardora User';
        $displayName = trim((string) (($googleUser['given_name'] ?? $googleUser['name'] ?? '') ?: $name));

        $user = User::query()->where('email', $email)->first();
        $isNewUser = false;

        if (! $user) {
            $isNewUser = true;
            $baseHandle = Str::slug($displayName ?: Str::before($email, '@'));
            $baseHandle = $baseHandle !== '' ? $baseHandle : 'collector';
            $handle = $this->uniqueHandle($baseHandle);

            $user = User::create([
                'name' => $name,
                'display_name' => $displayName,
                'handle' => $handle,
                'email' => $email,
                'city' => null,
                'bio' => null,
                'collector_tagline' => null,
                'favorite_categories' => [],
                'profile_visibility' => 'public',
                'password' => Str::random(40),
                'locale' => config('app.locale'),
                'trust_status' => 'basic',
                'email_verified_at' => now(),
                'last_seen_at' => now(),
            ]);

            $notifications->createForUser(
                $user->getKey(),
                'welcome',
                __('api.notifications.welcome_title'),
                __('api.notifications.welcome_body'),
                ['user_id' => $user->getKey()],
                'security'
            );
        } else {
            if (blank($user->display_name) && $displayName !== '') {
                $user->display_name = $displayName;
            }

            if (blank($user->name) && $name !== '') {
                $user->name = $name;
            }

            if (blank($user->handle)) {
                $baseHandle = Str::slug($displayName ?: Str::before($email, '@'));
                $baseHandle = $baseHandle !== '' ? $baseHandle : 'collector';
                $user->handle = $this->uniqueHandle($baseHandle, $user->getKey());
            }

            if (blank($user->email_verified_at)) {
                $user->email_verified_at = now();
            }

            $user->last_seen_at = now();
            $user->save();
        }

        return [
            $user->issueSingleSessionToken('cardora-google-web'),
            $isNewUser,
        ];
    }

    protected function uniqueHandle(string $baseHandle, ?int $ignoredUserId = null): string
    {
        $handle = $baseHandle;
        $suffix = 1;

        while (User::query()
            ->when($ignoredUserId, fn ($query) => $query->whereKeyNot($ignoredUserId))
            ->where('handle', $handle)
            ->exists()) {
            $handle = sprintf('%s-%d', $baseHandle, $suffix);
            $suffix++;
        }

        return $handle;
    }

    protected function buildFrontendCallbackUrl(array $payload): string
    {
        $baseUrl = (string) config(
            'services.frontend.google_auth_callback_url',
            rtrim((string) config('services.frontend.url', config('app.url')), '/') . '/auth/google/callback'
        );

        $fragment = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $separator = str_contains($baseUrl, '#') ? '&' : '#';

        return $baseUrl . $separator . $fragment;
    }
}
