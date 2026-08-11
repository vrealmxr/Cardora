<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DhlMydhlService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.dhl.enabled', false);
    }

    public function hasCredentials(): bool
    {
        return filled(config('services.dhl.username')) && filled(config('services.dhl.password'));
    }

    public function hasAccountNumber(): bool
    {
        return filled(config('services.dhl.account_number'));
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled() && $this->hasCredentials() && $this->hasAccountNumber();
    }

    public function createShipment(array $payload): array
    {
        try {
            return $this->request()
                ->post('/shipments', $payload)
                ->throw()
                ->json() ?? [];
        } catch (RequestException $exception) {
            $responseBody = $exception->response?->body();

            throw new RuntimeException(
                sprintf(
                    'DHL createShipment failed with status %s: %s',
                    $exception->response?->status() ?? 'unknown',
                    $responseBody ?: $exception->getMessage()
                ),
                previous: $exception
            );
        }
    }

    public function trackShipment(string $trackingNumber): array
    {
        return $this->request()
            ->get(sprintf('/shipments/%s/tracking', urlencode($trackingNumber)))
            ->throw()
            ->json() ?? [];
    }

    public function findServicePoints(array $query = []): array
    {
        $normalizedQuery = $query;
        $address = trim((string) ($normalizedQuery['address'] ?? ''));

        if ($address === '') {
            $addressParts = array_filter([
                $normalizedQuery['postalCode'] ?? null,
                $normalizedQuery['addressLocality'] ?? null,
            ], fn ($value) => filled($value));

            if ($addressParts !== []) {
                $normalizedQuery['address'] = implode(' ', $addressParts);
            }
        }

        $normalizedQuery['servicePointResults'] = $normalizedQuery['servicePointResults'] ?? 10;
        $normalizedQuery['language'] = $normalizedQuery['language'] ?? 'eng';

        return $this->request()
            ->get('/servicepoints', $normalizedQuery)
            ->throw()
            ->json() ?? [];
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('DHL MyDHL API credentials are not configured yet.');
        }

        $username = (string) config('services.dhl.username');
        $password = (string) config('services.dhl.password');
        $authorizationHeader = 'Basic '.base64_encode(sprintf('%s:%s', $username, $password));

        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withHeaders([
                'Authorization' => $authorizationHeader,
            ])
            ->withBasicAuth($username, $password);
    }

    protected function baseUrl(): string
    {
        $useTestEnvironment = (bool) config('services.dhl.use_test_environment', true);
        $baseUrl = $useTestEnvironment
            ? (string) config('services.dhl.test_base_url')
            : (string) config('services.dhl.base_url');

        return rtrim($baseUrl, '/');
    }
}
