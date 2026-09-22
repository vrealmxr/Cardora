<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the BoxNow Partner API (manual v1.65,
 * https://boxnow.gr/en/docs/api/partner-api/).
 *
 * BoxNow uses an OAuth2 client-credentials flow: you exchange client_id/client_secret for a
 * short-lived access token, then call the delivery endpoints with a Bearer token. Locker/
 * warehouse lookups (/origins, /destinations) live on a separate "locationapi" subdomain from
 * everything else (auth, delivery-requests, parcels), per the official docs.
 */
class BoxNowService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.boxnow.enabled', false);
    }

    public function hasCredentials(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
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
     * Fetch the current status of a parcel by its id (the "voucher"/tracking number returned
     * from createShipment). The Partner API only exposes a filtered list endpoint
     * (GET /api/v1/parcels?parcelId=...), not a GET-by-id route.
     */
    public function trackShipment(string $parcelId): array
    {
        $response = $this->request()
            ->get((string) config('services.boxnow.parcels_path', '/api/v1/parcels'), [
                'parcelId' => $parcelId,
                'limit' => 1,
            ])
            ->throw()
            ->json() ?? [];

        return data_get($response, 'data.0', []);
    }

    /**
     * Look up available BoxNow lockers / automated parcel machines (destinations).
     *
     * @param  array{latlng?: string, radius?: int, requiredSize?: int, locationType?: array<int, string>, name?: string}  $query
     */
    public function findLockers(array $query = []): array
    {
        return $this->locationRequest()
            ->get((string) config('services.boxnow.destinations_path', '/api/v1/destinations'), $query)
            ->throw()
            ->json() ?? [];
    }

    /**
     * List available pickup (warehouse) origins — mainly useful to confirm a configured
     * warehouse_id resolves to a real location.
     */
    public function findOrigins(array $query = []): array
    {
        return $this->locationRequest()
            ->get((string) config('services.boxnow.origins_path', '/api/v1/origins'), $query)
            ->throw()
            ->json() ?? [];
    }

    /**
     * Resolve a postal address to its closest BoxNow locker. The /destinations lookup only
     * supports filtering by lat/lng radius or an exact locker name — not by postal code/city —
     * so this is the API BoxNow actually provides for "find me a locker near this address".
     */
    public function checkAddressDelivery(array $payload): array
    {
        return $this->request()
            ->post('/api/v2/delivery-requests:checkAddressDelivery', $payload)
            ->throw()
            ->json() ?? [];
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BoxNow API credentials are not configured yet.');
        }

        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withToken($this->accessToken());

        $partnerId = $this->partnerId();

        return filled($partnerId) ? $request->withHeaders(['X-PartnerID' => (string) $partnerId]) : $request;
    }

    /**
     * The /origins and /destinations lookups live on a different host and don't require the
     * X-PartnerID header, but they do use the same bearer token.
     */
    protected function locationRequest(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BoxNow API credentials are not configured yet.');
        }

        return Http::baseUrl($this->locationBaseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withToken($this->accessToken());
    }

    protected function accessToken(): string
    {
        $cacheKey = sprintf(
            'boxnow:access_token:%s:%s',
            $this->isTestEnvironment() ? 'test' : 'live',
            md5((string) $this->clientId())
        );

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post((string) config('services.boxnow.auth_path', '/api/v1/auth-sessions'), [
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) $this->clientId(),
                    'client_secret' => (string) $this->clientSecret(),
                ])
                ->throw()
                ->json();

            $token = data_get($response, 'access_token');

            if (! is_string($token) || trim($token) === '') {
                throw new RuntimeException('BoxNow authentication did not return an access token.');
            }

            return trim($token);
        });
    }

    protected function isTestEnvironment(): bool
    {
        return (bool) config('services.boxnow.use_test_environment', true);
    }

    /**
     * BoxNow issues entirely separate client_id/client_secret/partner_id per environment —
     * these fall back to the "live" keys only when no *_test_* override is configured, so a
     * single-environment setup (e.g. only ever using stage) keeps working unchanged.
     */
    protected function clientId(): ?string
    {
        return $this->isTestEnvironment()
            ? (config('services.boxnow.test_client_id') ?: config('services.boxnow.client_id'))
            : config('services.boxnow.client_id');
    }

    protected function clientSecret(): ?string
    {
        return $this->isTestEnvironment()
            ? (config('services.boxnow.test_client_secret') ?: config('services.boxnow.client_secret'))
            : config('services.boxnow.client_secret');
    }

    protected function partnerId(): ?string
    {
        return $this->isTestEnvironment()
            ? (config('services.boxnow.test_partner_id') ?: config('services.boxnow.partner_id'))
            : config('services.boxnow.partner_id');
    }

    protected function baseUrl(): string
    {
        $baseUrl = $this->isTestEnvironment()
            ? (string) config('services.boxnow.test_base_url')
            : (string) config('services.boxnow.base_url');

        return rtrim($baseUrl, '/');
    }

    protected function locationBaseUrl(): string
    {
        $baseUrl = $this->isTestEnvironment()
            ? (string) config('services.boxnow.location_test_base_url')
            : (string) config('services.boxnow.location_base_url');

        return rtrim($baseUrl, '/');
    }
}
