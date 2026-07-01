<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the BoxNow partner API.
 *
 * BoxNow uses an OAuth2 client-credentials flow: you exchange client_id/client_secret for a
 * short-lived access token, then call the delivery endpoints with a Bearer token.
 *
 * Endpoint paths are configurable so they can be finalized against the official BoxNow API
 * documentation once partner credentials are provisioned, without touching the workflow code.
 */
class BoxNowService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.boxnow.enabled', false);
    }

    public function hasCredentials(): bool
    {
        return filled(config('services.boxnow.client_id')) && filled(config('services.boxnow.client_secret'));
    }

    public function hasWarehouse(): bool
    {
        return filled(config('services.boxnow.warehouse_id'));
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled() && $this->hasCredentials() && $this->hasWarehouse();
    }

    /**
     * Create a delivery request (parcel). Returns the raw BoxNow response.
     */
    public function createShipment(array $payload): array
    {
        return $this->request()
            ->post((string) config('services.boxnow.shipments_path', '/api/v1/delivery-requests'), $payload)
            ->throw()
            ->json() ?? [];
    }

    /**
     * Fetch the current status of a delivery request / parcel.
     */
    public function trackShipment(string $shipmentId): array
    {
        $path = rtrim((string) config('services.boxnow.shipments_path', '/api/v1/delivery-requests'), '/');

        return $this->request()
            ->get(sprintf('%s/%s', $path, urlencode($shipmentId)))
            ->throw()
            ->json() ?? [];
    }

    /**
     * Look up available BoxNow lockers / automated parcel machines (destinations).
     */
    public function findLockers(array $query = []): array
    {
        return $this->request()
            ->get((string) config('services.boxnow.lockers_path', '/api/v1/destinations/points'), $query)
            ->throw()
            ->json() ?? [];
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BoxNow API credentials are not configured yet.');
        }

        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withToken($this->accessToken());
    }

    protected function accessToken(): string
    {
        $cacheKey = sprintf('boxnow:access_token:%s', md5((string) config('services.boxnow.client_id')));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post((string) config('services.boxnow.auth_path', '/api/v1/auth-sessions'), [
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) config('services.boxnow.client_id'),
                    'client_secret' => (string) config('services.boxnow.client_secret'),
                ])
                ->throw()
                ->json();

            $token = data_get($response, 'access_token') ?? data_get($response, 'accessToken');

            if (! is_string($token) || trim($token) === '') {
                throw new RuntimeException('BoxNow authentication did not return an access token.');
            }

            return trim($token);
        });
    }

    protected function baseUrl(): string
    {
        $useTestEnvironment = (bool) config('services.boxnow.use_test_environment', true);
        $baseUrl = $useTestEnvironment
            ? (string) config('services.boxnow.test_base_url')
            : (string) config('services.boxnow.base_url');

        return rtrim($baseUrl, '/');
    }
}
