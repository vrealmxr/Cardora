<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use Illuminate\Support\Str;

class BoxNowShipmentWorkflowService extends AbstractShipmentWorkflowService
{
    public function __construct(protected BoxNowService $boxNow)
    {
    }

    public function carrierCode(): string
    {
        return 'boxnow';
    }

    public function isConfigured(): bool
    {
        return $this->boxNow->isConfigured();
    }

    protected function shippingProfilePrefix(): string
    {
        return 'boxnow_';
    }

    protected function shippingMethodKeyword(): string
    {
        return 'boxnow';
    }

    protected function autoReleaseDays(): int
    {
        return (int) config('services.boxnow.auto_release_days_after_delivery', 2);
    }

    protected function defaultPackageWeightKg(): float
    {
        return (float) config('services.boxnow.default_package_weight_kg', 0.5);
    }

    protected function createRemoteShipment(Order $order, array $context): array
    {
        return $this->boxNow->createShipment($this->buildCreateShipmentPayload($order, $context));
    }

    protected function trackRemoteShipment(string $trackingNumber): array
    {
        return $this->boxNow->trackShipment($trackingNumber);
    }

    /**
     * Build the BoxNow delivery-request payload.
     *
     * NOTE: The exact field names are aligned with the BoxNow partner API conventions and must
     * be confirmed against the official documentation once credentials are provisioned. The
     * carrier-agnostic $context (sender/recipient/package) already contains everything we need.
     */
    protected function buildCreateShipmentPayload(Order $order, array $context): array
    {
        $sender = $context['sender'];
        $recipient = $context['recipient'];
        $package = $context['package'];
        $servicePoint = $recipient['service_point'] ?? [];
        $lockerId = $servicePoint['id'] ?? $servicePoint['locker_id'] ?? null;

        $destination = filled($lockerId)
            ? [
                // Locker (APM) delivery.
                'type' => 'locker',
                'boxnowLockerId' => (string) $lockerId,
                'contactName' => $recipient['full_name'],
                'contactPhone' => $recipient['phone'],
                'email' => $recipient['email'] ?? null,
            ]
            : [
                // Home delivery.
                'type' => 'homeDelivery',
                'contactName' => $recipient['full_name'],
                'contactPhone' => $recipient['phone'],
                'email' => $recipient['email'] ?? null,
                'addressLine1' => $recipient['address_line_1'],
                'addressLine2' => $recipient['address_line_2'],
                'city' => $recipient['city'],
                'postalCode' => $recipient['postal_code'],
                'countryCode' => $recipient['country_code'],
            ];

        return [
            'orderNumber' => (string) $order->order_number,
            // The order is already paid through Stripe, so nothing is collected on delivery.
            'paymentMode' => 'prepaid',
            'amountToBeCollected' => 0,
            'invoiceValue' => round((float) ($order->subtotal ?: $order->total_amount), 2),
            'currency' => strtoupper((string) ($order->currency ?: 'EUR')),
            'allowReturn' => true,
            'origin' => [
                'locationId' => (string) config('services.boxnow.warehouse_id'),
                'contactName' => $sender['full_name'],
                'contactPhone' => $sender['phone'],
                'addressLine1' => $sender['address_line_1'],
                'addressLine2' => $sender['address_line_2'],
                'city' => $sender['city'],
                'postalCode' => $sender['postal_code'],
                'countryCode' => $sender['country_code'],
            ],
            'destination' => $destination,
            'items' => [
                [
                    'weight' => round((float) $package['weight_kg'], 2),
                    'length' => (int) round((float) $package['length_cm']),
                    'width' => (int) round((float) $package['width_cm']),
                    'height' => (int) round((float) $package['height_cm']),
                    'value' => round((float) ($order->subtotal ?: $order->total_amount), 2),
                    'description' => $this->shipmentDescription($order),
                ],
            ],
        ];
    }

    protected function extractTrackingNumber(array $response): ?string
    {
        $candidates = [
            data_get($response, 'id'),
            data_get($response, 'deliveryRequestId'),
            data_get($response, 'parcels.0.id'),
            data_get($response, 'parcels.0.trackingNumber'),
            data_get($response, 'parcels.0.voucher'),
            data_get($response, 'trackingNumber'),
            data_get($response, 'voucher'),
        ];

        foreach ($candidates as $candidate) {
            if ((is_string($candidate) || is_int($candidate)) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    protected function extractShipmentReference(array $response): ?string
    {
        $candidate = data_get($response, 'parcels.0.voucher')
            ?? data_get($response, 'voucher')
            ?? data_get($response, 'reference')
            ?? data_get($response, 'orderNumber');

        return filled($candidate) ? trim((string) $candidate) : null;
    }

    protected function extractTrackingEvents(array $response): array
    {
        $collections = [
            data_get($response, 'events'),
            data_get($response, 'parcels.0.events'),
            data_get($response, 'parcels.0.statusHistory'),
            data_get($response, 'statusHistory'),
            data_get($response, 'history'),
        ];

        foreach ($collections as $events) {
            if (! is_array($events) || $events === []) {
                continue;
            }

            return collect($events)
                ->map(function ($event) {
                    return [
                        'code' => $event['status']
                            ?? $event['statusCode']
                            ?? $event['state']
                            ?? $event['code']
                            ?? null,
                        'description' => $event['statusName']
                            ?? $event['description']
                            ?? $event['comment']
                            ?? $event['message']
                            ?? null,
                        'timestamp' => $event['timestamp']
                            ?? $event['datetime']
                            ?? $event['createdAt']
                            ?? $event['date']
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
            data_get($response, 'state'),
            data_get($response, 'parcels.0.status'),
            data_get($latestEvent, 'code'),
            data_get($latestEvent, 'description'),
        ]);
        $normalized = Str::lower(implode(' ', array_map(fn ($value) => (string) $value, $statusPool)));

        // BoxNow-specific status tokens (checked before generic keywords to avoid ambiguity
        // between "picked up from sender" and "picked up by customer").
        if (Str::contains($normalized, ['delivered_to_apm', 'at_apm', 'in_locker', 'in locker', 'ready_for_pickup', 'available_for_collection', 'available in locker'])) {
            return ShipmentStatus::ReadyForCollection->value;
        }

        if (Str::contains($normalized, ['delivered_to_customer', 'collected_by_customer', 'parcel_collected', 'delivered', 'completed'])) {
            return ShipmentStatus::Delivered->value;
        }

        if (Str::contains($normalized, ['returned', 'return', 'cancelled', 'canceled', 'failed', 'expired', 'lost', 'not_collected'])) {
            return ShipmentStatus::Exception->value;
        }

        if (Str::contains($normalized, ['in_transit', 'in-transit', 'in transit', 'handover', 'picked_up_from_sender', 'in_warehouse', 'sorting', 'on_route', 'out_for_delivery', 'shipped'])) {
            return ShipmentStatus::InTransit->value;
        }

        if ($matched = $this->matchStatusKeywords($normalized)) {
            return $matched;
        }

        if (filled($this->extractTrackingNumber($response))) {
            return ShipmentStatus::LabelCreated->value;
        }

        return ShipmentStatus::PendingLabel->value;
    }
}
