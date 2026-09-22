<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BoxNowService;
use App\Services\DhlMydhlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShippingPickupPointController extends Controller
{
    public function __construct(
        protected DhlMydhlService $dhl,
        protected BoxNowService $boxNow
    ) {
    }

    /**
     * Return pickup points (BoxNow lockers or DHL service points) for the requested carrier.
     *
     * Response shape:
     *   { "carrier": "boxnow", "available": true, "points": [ { id, name, address, city, postal_code, country_code } ] }
     *
     * When the carrier is not configured yet (no credentials), `available` is false and the
     * points list is empty, so the frontend can degrade gracefully to home delivery.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carrier' => ['required', 'string', 'in:dhl_express,boxnow'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'query' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $carrier = $validated['carrier'];

        if ($carrier === 'boxnow') {
            return $this->respondForBoxNow($validated);
        }

        return $this->respondForDhl($validated);
    }

    /**
     * BoxNow's /destinations lookup only filters by lat/lng + radius (or an exact locker name) —
     * it has no postal-code/city filter. So when the buyer only gave us an address (postal code,
     * city, street), we first resolve it to coordinates via the v2 "checkAddressDelivery" call
     * (which returns the single closest locker + its lat/lng), then use those coordinates to
     * pull a short list of nearby lockers from /destinations for the buyer to choose from.
     */
    protected function respondForBoxNow(array $validated): JsonResponse
    {
        if (! $this->boxNow->isConfigured()) {
            return $this->unavailable('boxnow');
        }

        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        if (blank($latitude) || blank($longitude)) {
            $resolved = $this->resolveCoordinatesFromAddress($validated);
            $latitude = $resolved['lat'] ?? null;
            $longitude = $resolved['lng'] ?? null;
        }

        try {
            $query = ['locationType' => ['apm']];

            if (filled($latitude) && filled($longitude)) {
                $query['latlng'] = sprintf('%s,%s', $latitude, $longitude);
                $query['radius'] = 15000;
            } elseif (filled($validated['query'] ?? null)) {
                $query['name'] = $validated['query'];
            }

            $raw = $this->boxNow->findLockers($query);
        } catch (Throwable $exception) {
            Log::warning('BoxNow locker lookup failed.', ['error' => $exception->getMessage()]);

            return $this->unavailable('boxnow');
        }

        return response()->json([
            'carrier' => 'boxnow',
            'available' => true,
            'points' => $this->normalizePoints($this->pluckList($raw)),
        ]);
    }

    /**
     * @return array{lat: ?float, lng: ?float}
     */
    protected function resolveCoordinatesFromAddress(array $validated): array
    {
        if (blank($validated['postal_code'] ?? null) && blank($validated['city'] ?? null)) {
            return ['lat' => null, 'lng' => null];
        }

        try {
            $countryCode = strtoupper((string) ($validated['country_code'] ?? 'GR'));

            $response = $this->boxNow->checkAddressDelivery(array_filter([
                'city' => $validated['city'] ?? null,
                'street' => $validated['query'] ?? null,
                'postalCode' => $validated['postal_code'] ?? null,
                'region' => $countryCode === 'GR' ? 'el-GR' : null,
                'compartmentSize' => 1,
            ], fn ($value) => $value !== null && $value !== ''));

            return [
                'lat' => data_get($response, 'lat'),
                'lng' => data_get($response, 'lng'),
            ];
        } catch (Throwable $exception) {
            Log::info('BoxNow address-to-locker resolution failed, falling back to unfiltered lookup.', [
                'error' => $exception->getMessage(),
            ]);

            return ['lat' => null, 'lng' => null];
        }
    }

    protected function respondForDhl(array $validated): JsonResponse
    {
        if (! $this->dhl->isConfigured()) {
            return $this->unavailable('dhl_express');
        }

        try {
            $raw = $this->dhl->findServicePoints(array_filter([
                'countryCode' => $validated['country_code'] ?? null,
                'postalCode' => $validated['postal_code'] ?? null,
                'addressLocality' => $validated['city'] ?? null,
                'address' => trim(implode(' ', array_filter([
                    $validated['postal_code'] ?? null,
                    $validated['city'] ?? null,
                    $validated['query'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''))),
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (Throwable $exception) {
            Log::warning('DHL service point lookup failed.', ['error' => $exception->getMessage()]);

            return $this->unavailable('dhl_express');
        }

        return response()->json([
            'carrier' => 'dhl_express',
            'available' => true,
            'points' => $this->normalizePoints($this->pluckList($raw)),
        ]);
    }

    protected function unavailable(string $carrier): JsonResponse
    {
        return response()->json([
            'carrier' => $carrier,
            'available' => false,
            'points' => [],
        ]);
    }

    /**
     * Carrier responses wrap the list under different keys; return whatever list we can find.
     */
    protected function pluckList(array $response): array
    {
        foreach (['data', 'points', 'lockers', 'servicePoints', 'results', 'items'] as $key) {
            $candidate = data_get($response, $key);
            if (is_array($candidate) && $candidate !== []) {
                return $candidate;
            }
        }

        // Some APIs return a bare list.
        return array_is_list($response) ? $response : [];
    }

    /**
     * @param  array<int, mixed>  $points
     * @return array<int, array<string, mixed>>
     */
    protected function normalizePoints(array $points): array
    {
        return collect($points)
            ->map(function ($point) {
                if (! is_array($point)) {
                    return null;
                }

                $id = data_get($point, 'id')
                    ?? data_get($point, 'boxnowLockerId')
                    ?? data_get($point, 'lockerId')
                    ?? data_get($point, 'servicePointId')
                    ?? data_get($point, 'facilityId');

                if (blank($id)) {
                    return null;
                }

                $address = data_get($point, 'addressLine1')
                    ?? data_get($point, 'address.addressLine1')
                    ?? data_get($point, 'street')
                    ?? data_get($point, 'address.streetAddress')
                    ?? data_get($point, 'place.address.streetAddress');

                return [
                    'id' => (string) $id,
                    'name' => (string) (
                        data_get($point, 'name')
                        ?? data_get($point, 'localName')
                        ?? data_get($point, 'servicePointName')
                        ?? data_get($point, 'title')
                        ?? data_get($point, 'displayName')
                        ?? data_get($point, 'place.name')
                        ?? $id
                    ),
                    'address' => $address ? (string) $address : null,
                    'city' => data_get($point, 'city')
                        ?? data_get($point, 'address.city')
                        ?? data_get($point, 'addressLocality')
                        ?? data_get($point, 'address.city')
                        ?? data_get($point, 'place.address.addressLocality'),
                    'postal_code' => data_get($point, 'postalCode')
                        ?? data_get($point, 'address.zipCode')
                        ?? data_get($point, 'zip')
                        ?? data_get($point, 'address.postalCode')
                        ?? data_get($point, 'place.address.postalCode'),
                    'country_code' => data_get($point, 'country')
                        ?? data_get($point, 'countryCode')
                        ?? data_get($point, 'address.country')
                        ?? data_get($point, 'address.countryCode')
                        ?? data_get($point, 'place.address.countryCode'),
                    'distance' => data_get($point, 'distance') ?? data_get($point, 'distanceInMeters'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
