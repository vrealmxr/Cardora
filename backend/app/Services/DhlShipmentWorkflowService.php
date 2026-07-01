<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use Illuminate\Support\Str;

class DhlShipmentWorkflowService extends AbstractShipmentWorkflowService
{
    public function __construct(protected DhlMydhlService $dhl)
    {
    }

    public function carrierCode(): string
    {
        return 'dhl_express';
    }

    public function isConfigured(): bool
    {
        return $this->dhl->isConfigured();
    }

    /** Backwards-compatible alias for the carrier-agnostic routing check. */
    public function shouldUseDhlForOrder(Order $order): bool
    {
        return $this->orderUsesCarrier($order);
    }

    protected function shippingProfilePrefix(): string
    {
        return 'dhl_';
    }

    protected function shippingMethodKeyword(): string
    {
        return 'dhl express';
    }

    protected function autoReleaseDays(): int
    {
        return (int) config('services.dhl.auto_release_days_after_delivery', 2);
    }

    protected function defaultPackageWeightKg(): float
    {
        return (float) config('services.dhl.default_package_weight_kg', 0.5);
    }

    protected function createRemoteShipment(Order $order, array $context): array
    {
        return $this->dhl->createShipment($this->buildCreateShipmentPayload($order, $context));
    }

    protected function trackRemoteShipment(string $trackingNumber): array
    {
        return $this->dhl->trackShipment($trackingNumber);
    }

    protected function buildCreateShipmentPayload(Order $order, array $context): array
    {
        $sender = $context['sender'];
        $recipient = $context['recipient'];
        $package = $context['package'];

        $plannedShipping = now()->addMinutes(15);

        return [
            // MyDHL API expects the "YYYY-MM-DDTHH:mm:ss GMT+hh:mm" pattern, not the ISO/atom format.
            'plannedShippingDateAndTime' => $plannedShipping->format('Y-m-d\TH:i:s').' GMT'.$plannedShipping->format('P'),
            'pickup' => [
                'isRequested' => false,
            ],
            'productCode' => (string) config('services.dhl.default_product_code', 'N'),
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => (string) config('services.dhl.account_number'),
                ],
            ],
            'customerDetails' => [
                'shipperDetails' => [
                    'postalAddress' => [
                        'postalCode' => $sender['postal_code'],
                        'cityName' => $sender['city'],
                        'countryCode' => $sender['country_code'],
                        'addressLine1' => $sender['address_line_1'],
                        'addressLine2' => $sender['address_line_2'],
                    ],
                    'contactInformation' => [
                        'fullName' => $sender['full_name'],
                        'phone' => $sender['phone'],
                    ],
                ],
                'receiverDetails' => [
                    'postalAddress' => [
                        'postalCode' => $recipient['postal_code'],
                        'cityName' => $recipient['city'],
                        'countryCode' => $recipient['country_code'],
                        'addressLine1' => $recipient['address_line_1'],
                        'addressLine2' => $recipient['address_line_2'],
                    ],
                    'contactInformation' => [
                        'fullName' => $recipient['full_name'],
                        'phone' => $recipient['phone'],
                    ],
                ],
            ],
            'content' => [
                'packages' => [
                    [
                        'weight' => round((float) $package['weight_kg'], 2),
                        'dimensions' => [
                            'length' => (int) round((float) $package['length_cm']),
                            'width' => (int) round((float) $package['width_cm']),
                            'height' => (int) round((float) $package['height_cm']),
                        ],
                    ],
                ],
                'isCustomsDeclarable' => false,
                'description' => $this->shipmentDescription($order),
                'declaredValue' => round((float) ($order->subtotal ?: $order->total_amount), 2),
                'declaredValueCurrency' => strtoupper((string) ($order->currency ?: 'EUR')),
                'unitOfMeasurement' => 'metric',
            ],
            'outputImageProperties' => [
                'printerDPI' => 300,
                'encodingFormat' => 'pdf',
                'imageOptions' => [
                    [
                        'typeCode' => 'label',
                        'templateName' => 'ECOM26_84_A4_001',
                    ],
                ],
            ],
            'references' => [
                [
                    'value' => (string) $order->order_number,
                    'typeCode' => 'CU',
                ],
            ],
        ];
    }

    protected function extractTrackingNumber(array $response): ?string
    {
        $candidates = [
            data_get($response, 'shipmentTrackingNumber'),
            data_get($response, 'shipmentTrackingNumbers.0'),
            data_get($response, 'trackingNumber'),
            data_get($response, 'packages.0.trackingNumber'),
            data_get($response, 'documents.0.trackingNumber'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function extractShipmentReference(array $response): ?string
    {
        $candidate = data_get($response, 'dispatchConfirmationNumber')
            ?? data_get($response, 'shipmentReference')
            ?? data_get($response, 'reference');

        return is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : null;
    }

    protected function extractTrackingEvents(array $response): array
    {
        $collections = [
            data_get($response, 'events'),
            data_get($response, 'shipments.0.events'),
            data_get($response, 'shipments.0.checkpoints'),
            data_get($response, 'checkpoints'),
        ];

        foreach ($collections as $events) {
            if (! is_array($events) || $events === []) {
                continue;
            }

            return collect($events)
                ->map(function ($event) {
                    return [
                        'code' => $event['statusCode']
                            ?? $event['status']
                            ?? $event['code']
                            ?? null,
                        'description' => $event['description']
                            ?? $event['status']
                            ?? $event['statusDescription']
                            ?? $event['serviceArea']
                            ?? null,
                        'timestamp' => $event['timestamp']
                            ?? $event['dateTime']
                            ?? $event['time']
                            ?? null,
                    ];
                })
                ->filter(fn (array $event) => filled($event['description']) || filled($event['code']))
                ->values()
                ->all();
        }

        return [];
    }

    protected function resolveShipmentStatus(array $response, ?array $latestEvent): string
    {
        $statusPool = array_filter([
            data_get($response, 'status'),
            data_get($response, 'statusCode'),
            data_get($response, 'shipments.0.status'),
            data_get($latestEvent, 'code'),
            data_get($latestEvent, 'description'),
        ]);
        $normalized = Str::lower(implode(' ', array_map(fn ($value) => (string) $value, $statusPool)));

        if ($matched = $this->matchStatusKeywords($normalized)) {
            return $matched;
        }

        if (filled($this->extractTrackingNumber($response))) {
            return ShipmentStatus::LabelCreated->value;
        }

        return ShipmentStatus::PendingLabel->value;
    }
}
