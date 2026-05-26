<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketplaceBootstrapService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class BootstrapController extends Controller
{
    public function __invoke(Request $request, MarketplaceBootstrapService $bootstrapService)
    {
        $requestId = (string) Str::uuid();
        $responseHeaders = [
            'X-Request-Id' => $requestId,
            // Bootstrap payload changes frequently and should never be cached by browsers/CDN.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        try {
            $payload = $bootstrapService->build(
                $this->resolveAuthUser($request),
                app()->getLocale()
            );

            return response()->json(
                ['data' => $payload],
                200,
                $responseHeaders,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (AuthenticationException $exception) {
            return response()->json(
                ['message' => 'Unauthenticated.'],
                401,
                $responseHeaders,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (Throwable $exception) {
            logger()->error('bootstrap.failed', [
                'request_id' => $requestId,
                'url' => $request->fullUrl(),
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(
                [
                    'message' => 'Bootstrap failed.',
                    'request_id' => $requestId,
                ],
                500,
                $responseHeaders,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
        }
    }

    protected function resolveAuthUser(Request $request): ?User
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (! $accessToken || ! ($accessToken->tokenable instanceof User)) {
            throw new AuthenticationException();
        }

        return $accessToken->tokenable;
    }
}
