<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ApnsPushNotificationService
{
    public function __construct(
        protected UserNotificationPreferenceService $preferences
    ) {
    }

    public function sendIfAllowed(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $preferenceCategory = null
    ): void {
        if (! $this->isConfigured()) {
            return;
        }

        if ($preferenceCategory && ! $this->preferences->allowsPush($userId, $preferenceCategory)) {
            return;
        }

        $tokens = UserDeviceToken::query()
            ->where('user_id', $userId)
            ->where('platform', 'ios')
            ->pluck('token')
            ->filter(fn ($token) => is_string($token) && $token !== '')
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $payload = [
            'aps' => array_filter([
                'alert' => array_filter([
                    'title' => $title,
                    'body' => $body,
                ]),
                'sound' => 'default',
                'badge' => 1,
            ]),
            'type' => $type,
            'data' => $data !== [] ? $data : null,
        ];

        foreach ($tokens as $token) {
            $this->sendToToken($token, $payload);
        }
    }

    protected function sendToToken(string $deviceToken, array $payload): void
    {
        try {
            $jwt = $this->makeJwt();
            if ($jwt === null) {
                return;
            }

            $url = rtrim((string) config('services.apns.base_url'), '/').'/3/device/'.$deviceToken;

            $response = Http::withHeaders([
                'authorization' => 'bearer '.$jwt,
                'apns-topic' => (string) config('services.apns.bundle_id', 'vrealm.Cardora'),
                'apns-push-type' => 'alert',
                'apns-priority' => '10',
            ])
                ->withOptions(['version' => 2.0])
                ->acceptJson()
                ->timeout(10)
                ->withBody((string) json_encode($payload, JSON_UNESCAPED_UNICODE), 'application/json')
                ->post($url);

            if ($response->status() === 410) {
                UserDeviceToken::query()
                    ->where('platform', 'ios')
                    ->where('token', $deviceToken)
                    ->delete();
            } elseif (! $response->successful()) {
                report(new \RuntimeException(sprintf(
                    'APNs push failed (%s): %s',
                    $response->status(),
                    $response->body()
                )));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function makeJwt(): ?string
    {
        $keyId = trim((string) config('services.apns.key_id'));
        $teamId = trim((string) config('services.apns.team_id'));
        $privateKey = $this->resolvePrivateKey();

        if ($keyId === '' || $teamId === '' || $privateKey === null) {
            return null;
        }

        $header = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'kid' => $keyId], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => time(),
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$claims;
        $signature = '';

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            return null;
        }

        openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    protected function resolvePrivateKey(): ?string
    {
        $inline = trim((string) config('services.apns.private_key'));
        if ($inline !== '') {
            return str_contains($inline, '\\n')
                ? str_replace('\\n', PHP_EOL, $inline)
                : $inline;
        }

        $path = trim((string) config('services.apns.private_key_path'));
        if ($path !== '' && is_readable($path)) {
            return (string) file_get_contents($path);
        }

        return null;
    }

    protected function isConfigured(): bool
    {
        return trim((string) config('services.apns.key_id')) !== ''
            && trim((string) config('services.apns.team_id')) !== ''
            && $this->resolvePrivateKey() !== null;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
