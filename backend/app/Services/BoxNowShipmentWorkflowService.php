<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Order;

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

    /**
     * BoxNow's tracking response is a single flat parcel record (see trackRemoteShipment /
     * BoxNowService::trackShipment), not wrapped like the create-shipment response.
     */
    protected function trackRemoteShipment(string $trackingNumber): array
    {
        return $this->boxNow->trackShipment($trackingNumber);
    }

    /**
     * Build the BoxNow delivery-request payload for POST /api/v1/delivery-requests, per the
     * official Partner API manual (v1.65). BoxNow is APM/locker-only through this integration —
     * there is no "home delivery" destination type (attempting one returns error P415 "You are
     * not allowed to create delivery to home address"), which matches the checkout UI already
     * forcing pickup-only delivery for this carrier.
     */
    protected function buildCreateShipmentPayload(Order $order, array $context): array
    {
        $sender = $context['sender'];
        $recipient = $context['recipient'];
        $package = $context['package'];
        $servicePoint = $recipient['service_point'] ?? [];
        $lockerId = $servicePoint['id'] ?? $servicePoint['locker_id'] ?? null;
        $itemValue = (float) ($order->subtotal ?: $order->total_amount);

        return [
            'orderNumber' => (string) $order->order_number,
            'invoiceValue' => number_format($itemValue, 2, '.', ''),
            // The order is already paid through Stripe, so nothing is collected on delivery.
            'paymentMode' => 'prepaid',
            'amountToBeCollected' => '0.00',
            'allowReturn' => true,
            'origin' => [
                'contactNumber' => $this->normalizePhoneNumber($sender['phone'] ?? '', $sender['country_code'] ?? 'GR'),
                'contactEmail' => $order->seller?->email,
                'contactName' => $sender['full_name'],
                // Our own warehouse/pickup-point id, as returned by GET /origins.
                'locationId' => (string) config('services.boxnow.warehouse_id'),
            ],
            'destination' => [
                'contactNumber' => $this->normalizePhoneNumber($recipient['phone'] ?? '', $recipient['country_code'] ?? 'GR'),
                'contactEmail' => $order->buyer?->email,
                'contactName' => $recipient['full_name'],
                // The locker (APM) id the buyer picked at checkout, as returned by GET /destinations.
                'locationId' => (string) $lockerId,
            ],
            'items' => [
                [
                    'id' => (string) $order->getKey(),
                    'name' => $this->shipmentDescription($order),
                    'value' => number_format($itemValue, 2, '.', ''),
                    // BoxNow expects grams, not kilograms.
                    'weight' => (int) round(((float) $package['weight_kg']) * 1000),
                    'compartmentSize' => $this->resolveCompartmentSize($package),
                ],
            ],
        ];
    }

    /**
     * BoxNow rejects phone numbers that aren't in full international format (API error P405:
     * "Make sure you are sending the phone number in full international format, e.g.
     * +30 xx x xxx xxxx"). Checkout/seller-origin phone fields are plain national numbers
     * (e.g. "6957269540"), so prefix the calling code for the given country before sending.
     */
    protected function normalizePhoneNumber(string $phone, string $countryCode): string
    {
        $trimmed = trim($phone);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '+')) {
            return $trimmed;
        }

        $digitsOnly = preg_replace('/\D+/', '', $trimmed) ?? '';
        $digitsOnly = ltrim($digitsOnly, '0');

        $callingCodes = [
            'GR' => '30',
            'CY' => '357',
            'BG' => '359',
            'HR' => '385',
        ];
        $callingCode = $callingCodes[strtoupper($countryCode)] ?? $callingCodes['GR'];

        return '+' . $callingCode . $digitsOnly;
    }

    /**
     * Map our cm-based package dimensions onto BoxNow's 3 locker compartment sizes
     * (1=small, 2=medium, 3=large — see API error P406). Thresholds mirror the 4 legacy parcel
     * presets in AbstractShipmentWorkflowService::legacyParcelDimensions() (mini/small collapse
     * into BoxNow's "small" compartment, since BoxNow itself only has 3 sizes).
     */
    protected function resolveCompartmentSize(array $package): int
    {
        $heightCm = (float) ($package['height_cm'] ?? 0);
        $lengthCm = (float) ($package['length_cm'] ?? 0);

        if ($heightCm <= 0) {
            return 2;
        }

        if ($heightCm > 20) {
            return 3;
        }

        return $lengthCm > 20 ? 2 : 1;
    }

    protected function extractTrackingNumber(array $response): ?string
    {
        // parcels.0.id: the create-shipment response shape ({id: deliveryRequestId, parcels: [...]});
        // id: the tracking response shape, which is already a single flat parcel record.
        $candidates = [
            data_get($response, 'parcels.0.id'),
            data_get($response, 'id'),
        ];

        foreach ($candidates as $candidate) {
            if ((is_string($candidate) || is_int($candidate)) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    /**
     * Only called from createShipmentForOrder() with the raw create-shipment response, whose
     * top-level "id" is BoxNow's delivery-request id (distinct from the parcel/tracking id).
     */
    protected function extractShipmentReference(array $response): ?string
    {
        $candidate = data_get($response, 'id');

        return filled($candidate) ? trim((string) $candidate) : null;
    }

    /**
     * Only called from syncTrackingForOrder() with the flat parcel record; its event history
     * lives under deliveryRequest.events, each {type, locationDisplayName, postalCode, createTime}.
     */
    protected function extractTrackingEvents(array $response): array
    {
        $events = data_get($response, 'deliveryRequest.events', []);

        if (! is_array($events) || $events === []) {
            return [];
        }

        return collect($events)
            ->map(fn ($event) => [
                'code' => $event['type'] ?? null,
                'description' => $event['locationDisplayName'] ?? ($event['type'] ?? null),
                'timestamp' => $event['createTime'] ?? null,
            ])
            ->filter(fn (array $event) => filled($event['code']))
            ->values()
            ->all();
    }

    /**
     * BoxNow parcel "state" values, per the official Partner API manual (v1.65) appendix 6.5.3:
     * new, intransit, in-depot, wait-for-load, in-final-destination, delivered, accepted-for-return,
     * expired-return, returned, cancelled, lost, missing.
     *
     * "in-final-destination" explicitly means "reached the locker, waiting for pickup" — the
     * customer has NOT collected it yet. Only "delivered" means the customer actually collected
     * it from the locker. This is the carrier-specific distinction the whole ready_for_collection
     * vs delivered_at split (see AbstractShipmentWorkflowService::syncTrackingForOrder) exists for.
     */
    protected function resolveShipmentStatus(array $response, ?array $latestEvent): string
    {
        $state = (string) (data_get($response, 'state') ?? data_get($latestEvent, 'code') ?? '');

        // The written API manual spells several of these without hyphens (e.g. "intransit"),
        // but the real production API has been observed returning hyphenated forms instead
        // (e.g. "in-transit") — match both spellings defensively rather than trust the manual.
        return match ($state) {
            'in-final-destination', 'final-destination' => ShipmentStatus::ReadyForCollection->value,
            'delivered' => ShipmentStatus::Delivered->value,
            'intransit', 'in-transit', 'in-depot', 'wait-for-load', 'accepted-for-return', 'accepted-to-locker' => ShipmentStatus::InTransit->value,
            'returned', 'expired-return', 'expired', 'cancelled', 'canceled', 'lost', 'missing' => ShipmentStatus::Exception->value,
            default => filled($this->extractTrackingNumber($response))
                ? ShipmentStatus::LabelCreated->value
                : ShipmentStatus::PendingLabel->value,
        };
    }

    /**
     * BoxNow's webhook "event" vocabulary (Webhook-Based Parcel Tracking Guide v1.4) is a
     * *different* enum than the /parcels REST "state" field handled in resolveShipmentStatus()
     * above (e.g. "final-destination" here vs "in-final-destination" there, "canceled" here vs
     * "cancelled" there) — map it separately rather than trying to reuse the same match.
     */
    protected function resolveWebhookEventStatus(string $event): ?string
    {
        return match ($event) {
            'final-destination' => ShipmentStatus::ReadyForCollection->value,
            'delivered' => ShipmentStatus::Delivered->value,
            'in-depot', 'accepted-for-return', 'accepted-to-locker' => ShipmentStatus::InTransit->value,
            'expired', 'returned', 'canceled', 'missing' => ShipmentStatus::Exception->value,
            'new' => ShipmentStatus::LabelCreated->value,
            default => null,
        };
    }
}
