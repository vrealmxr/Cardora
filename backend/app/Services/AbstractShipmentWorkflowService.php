<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Listing;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Carrier-agnostic shipment lifecycle.
 *
 * Concrete carriers (DHL, BoxNow, ...) only implement how to talk to their API and how to
 * translate their responses into our normalized tracking number / events / ShipmentStatus.
 * Everything about *when* an order becomes shipped / delivered and *when* funds auto-release
 * lives here, so every carrier behaves identically for the buyer and seller.
 */
abstract class AbstractShipmentWorkflowService
{
    /** Internal carrier identifier stored on orders.shipping_carrier (e.g. dhl_express, boxnow). */
    abstract public function carrierCode(): string;

    /** True once the carrier has every credential required to create/track shipments. */
    abstract public function isConfigured(): bool;

    /** Call the carrier API to create the shipment and return the raw response array. */
    abstract protected function createRemoteShipment(Order $order, array $context): array;

    /** Call the carrier API to fetch tracking for a shipment and return the raw response array. */
    abstract protected function trackRemoteShipment(string $trackingNumber): array;

    /** Pull the carrier tracking / voucher number out of a create-shipment or tracking response. */
    abstract protected function extractTrackingNumber(array $response): ?string;

    /** Pull an optional carrier-side reference (dispatch confirmation, order id, ...). */
    abstract protected function extractShipmentReference(array $response): ?string;

    /**
     * Normalize the carrier tracking events into a list of
     * ['code' => ?string, 'description' => ?string, 'timestamp' => ?string].
     *
     * @return array<int, array{code: ?string, description: ?string, timestamp: ?string}>
     */
    abstract protected function extractTrackingEvents(array $response): array;

    /** Map a carrier response + latest event into one of the ShipmentStatus values. */
    abstract protected function resolveShipmentStatus(array $response, ?array $latestEvent): string;

    /** Number of days after delivery before funds auto-release. */
    abstract protected function autoReleaseDays(): int;

    /** Fallback parcel weight (kg) when a listing has none. */
    abstract protected function defaultPackageWeightKg(): float;

    /** shipping_profile prefix that routes an order to this carrier (e.g. "dhl_", "boxnow_"). */
    abstract protected function shippingProfilePrefix(): string;

    /** shipping_methods keyword that routes an order to this carrier (e.g. "dhl express", "boxnow"). */
    abstract protected function shippingMethodKeyword(): string;

    public function createShipmentForOrder(Order $order): Order
    {
        $order->loadMissing(['seller', 'items.listing.product']);

        if (! $this->orderUsesCarrier($order)) {
            return $order->fresh();
        }

        if (! $this->isConfigured()) {
            return $this->markShipmentPending($order, 'credentials_pending');
        }

        if (filled($order->shipment_tracking_number)) {
            return $order->fresh();
        }

        $shipmentContext = $this->buildShipmentContext($order);
        if ($shipmentContext['errors'] !== []) {
            return $this->markShipmentPending(
                $order,
                'missing_prerequisites',
                ['errors' => $shipmentContext['errors']]
            );
        }

        try {
            $response = $this->createRemoteShipment($order, $shipmentContext);
        } catch (Throwable $exception) {
            Log::warning('Shipment creation failed.', [
                'carrier' => $this->carrierCode(),
                'order_id' => $order->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return $this->markShipmentPending(
                $order,
                'shipment_creation_failed',
                ['error' => $exception->getMessage()]
            );
        }

        $trackingNumber = $this->extractTrackingNumber($response);
        $status = $trackingNumber ? ShipmentStatus::LabelCreated->value : ShipmentStatus::PendingLabel->value;

        return DB::transaction(function () use ($order, $response, $trackingNumber, $status) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $shipmentMetadata = $lockedOrder->shipment_metadata ?? [];

            $lockedOrder->forceFill([
                'shipment_status' => $status,
                'tracking_number' => $trackingNumber ?: $lockedOrder->tracking_number,
                'shipment_tracking_number' => $trackingNumber,
                'shipment_reference' => $this->extractShipmentReference($response),
                'shipment_synced_at' => now(),
                'shipment_metadata' => array_merge($shipmentMetadata, [
                    'create_shipment_response' => $response,
                    'shipment_blocker' => null,
                ]),
            ])->save();

            return $lockedOrder->fresh();
        });
    }

    public function syncTrackingForOrder(Order $order): Order
    {
        $trackingNumber = $order->shipment_tracking_number ?: $order->tracking_number;

        if (! $this->isConfigured() || blank($trackingNumber)) {
            return $order->fresh();
        }

        try {
            $response = $this->trackRemoteShipment($trackingNumber);
        } catch (Throwable $exception) {
            Log::warning('Tracking sync failed.', [
                'carrier' => $this->carrierCode(),
                'order_id' => $order->getKey(),
                'tracking_number' => $trackingNumber,
                'error' => $exception->getMessage(),
            ]);

            return $this->markShipmentPending(
                $order,
                'tracking_sync_failed',
                ['error' => $exception->getMessage()]
            );
        }

        $events = $this->extractTrackingEvents($response);
        $latestEvent = $this->latestTrackingEvent($events);
        $shipmentStatus = $this->resolveShipmentStatus($response, $latestEvent);
        $latestEventAt = $this->parseEventTimestamp($latestEvent['timestamp'] ?? null);
        $deliveredAt = $shipmentStatus === ShipmentStatus::Delivered->value
            ? ($latestEventAt ?: now())
            : $order->delivered_at;
        $shippedAt = in_array($shipmentStatus, [
            ShipmentStatus::InTransit->value,
            ShipmentStatus::ReadyForCollection->value,
            ShipmentStatus::Delivered->value,
        ], true)
            ? ($order->shipped_at ?: ($latestEventAt ?: now()))
            : $order->shipped_at;

        return DB::transaction(function () use (
            $order,
            $response,
            $trackingNumber,
            $latestEvent,
            $latestEventAt,
            $shipmentStatus,
            $shippedAt,
            $deliveredAt
        ) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $shipmentMetadata = $lockedOrder->shipment_metadata ?? [];
            $autoReleaseAt = $lockedOrder->auto_release_at;

            if (
                $shipmentStatus === ShipmentStatus::Delivered->value
                && $lockedOrder->status === OrderStatus::PaidPendingRelease->value
                && $lockedOrder->auto_release_at === null
            ) {
                $deliveryMoment = $deliveredAt instanceof Carbon ? $deliveredAt->copy() : now();
                $autoReleaseAt = $deliveryMoment->addDays(max(1, $this->autoReleaseDays()));
            }

            $lockedOrder->forceFill([
                'shipment_status' => $shipmentStatus,
                'tracking_number' => $trackingNumber,
                'shipment_tracking_number' => $trackingNumber,
                'shipped_at' => $shippedAt,
                'delivered_at' => $deliveredAt,
                'auto_release_at' => $autoReleaseAt,
                'shipment_last_event_code' => Arr::get($latestEvent, 'code'),
                'shipment_last_event_description' => Arr::get($latestEvent, 'description'),
                'shipment_last_event_at' => $latestEventAt,
                'shipment_synced_at' => now(),
                'shipment_metadata' => array_merge($shipmentMetadata, [
                    'tracking_response' => $response,
                    'shipment_blocker' => null,
                ]),
            ])->save();

            return $lockedOrder->fresh();
        });
    }

    public function orderUsesCarrier(Order $order): bool
    {
        $order->loadMissing(['items.listing']);

        return $order->items
            ->pluck('listing')
            ->filter()
            ->contains(function (Listing $listing) {
                $profile = (string) ($listing->shipping_profile ?? '');
                $methods = collect($listing->shipping_methods ?? [])->map(fn ($value) => Str::lower((string) $value));

                return Str::startsWith($profile, $this->shippingProfilePrefix())
                    || $methods->contains($this->shippingMethodKeyword());
            });
    }

    protected function markShipmentPending(Order $order, string $reason, array $context = []): Order
    {
        return DB::transaction(function () use ($order, $reason, $context) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $shipmentMetadata = $lockedOrder->shipment_metadata ?? [];

            $lockedOrder->forceFill([
                'shipment_status' => $lockedOrder->shipment_status ?: ShipmentStatus::PendingLabel->value,
                'shipment_synced_at' => now(),
                'shipment_metadata' => array_merge($shipmentMetadata, [
                    'shipment_blocker' => $reason,
                    'shipment_blocker_context' => $context,
                ]),
            ])->save();

            return $lockedOrder->fresh();
        });
    }

    protected function buildShipmentContext(Order $order): array
    {
        $sender = $this->normalizeSender($order);
        $recipient = $this->normalizeRecipient($order);
        $package = $this->normalizePackage($order);
        $errors = [];

        foreach (['full_name', 'phone', 'address_line_1', 'city', 'postal_code', 'country_code'] as $field) {
            if (blank($sender[$field] ?? null)) {
                $errors[] = sprintf('sender.%s', $field);
            }

            if (blank($recipient[$field] ?? null)) {
                $errors[] = sprintf('recipient.%s', $field);
            }
        }

        foreach (['weight_kg', 'length_cm', 'width_cm', 'height_cm'] as $field) {
            if (! is_numeric($package[$field] ?? null) || (float) $package[$field] <= 0) {
                $errors[] = sprintf('package.%s', $field);
            }
        }

        return [
            'sender' => $sender,
            'recipient' => $recipient,
            'package' => $package,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    protected function normalizeSender(Order $order): array
    {
        $seller = $order->seller;
        $origin = is_array($seller?->shipping_origin) ? $seller->shipping_origin : [];

        return [
            'full_name' => $origin['full_name'] ?? $seller?->display_name ?? $seller?->name,
            'phone' => $origin['phone'] ?? $seller?->phone,
            'address_line_1' => $origin['address_line_1'] ?? null,
            'address_line_2' => $origin['address_line_2'] ?? null,
            'city' => $origin['city'] ?? $seller?->city,
            'postal_code' => $origin['postal_code'] ?? null,
            'country' => $origin['country'] ?? 'Greece',
            'country_code' => Str::upper((string) ($origin['country_code'] ?? 'GR')),
        ];
    }

    protected function normalizeRecipient(Order $order): array
    {
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];
        $servicePoint = is_array($address['service_point'] ?? null) ? $address['service_point'] : [];

        return [
            'full_name' => $address['full_name'] ?? null,
            'phone' => $address['phone'] ?? null,
            'address_line_1' => $servicePoint['address'] ?? ($address['address_line_1'] ?? null),
            'address_line_2' => $address['address_line_2'] ?? null,
            'city' => $address['city'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? 'Greece',
            'country_code' => Str::upper((string) ($address['country_code'] ?? 'GR')),
            'delivery_type' => $address['delivery_type'] ?? 'home_delivery',
            'service_point' => $servicePoint,
        ];
    }

    protected function normalizePackage(Order $order): array
    {
        $listing = $order->items->pluck('listing')->filter()->first();
        $shipping = data_get($listing?->attributes, 'shipping', []);
        $package = data_get($shipping, 'package', []);

        $dimensions = $this->legacyParcelDimensions(
            data_get($shipping, 'domestic.parcel_type') ?: data_get($shipping, 'domestic.parcelType')
        );
        $defaultWeight = $this->defaultPackageWeightKg();

        return [
            'weight_kg' => $this->positiveFloat($package['weight_kg'] ?? $package['weightKg'] ?? null) ?: $defaultWeight,
            'length_cm' => $this->positiveFloat($package['length_cm'] ?? $package['lengthCm'] ?? null) ?: ($dimensions['length_cm'] ?? null),
            'width_cm' => $this->positiveFloat($package['width_cm'] ?? $package['widthCm'] ?? null) ?: ($dimensions['width_cm'] ?? null),
            'height_cm' => $this->positiveFloat($package['height_cm'] ?? $package['heightCm'] ?? null) ?: ($dimensions['height_cm'] ?? null),
        ];
    }

    protected function latestTrackingEvent(array $events): ?array
    {
        return collect($events)
            ->map(function (array $event) {
                return [
                    ...$event,
                    '_parsed_at' => $this->parseEventTimestamp($event['timestamp'] ?? null)?->timestamp,
                ];
            })
            ->sortBy('_parsed_at')
            ->last();
    }

    /**
     * Shared free-text status matcher. Kept intentionally conservative so it matches the
     * original DHL behaviour exactly; carriers that expose structured status codes should
     * override resolveShipmentStatus() instead of relying on this.
     *
     * Returns a ShipmentStatus value or null when nothing matches.
     */
    protected function matchStatusKeywords(string $normalized): ?string
    {
        if (Str::contains($normalized, ['available for collection', 'ready for collection', 'service point'])) {
            return ShipmentStatus::ReadyForCollection->value;
        }

        if (Str::contains($normalized, ['delivered', 'signed', 'collected by receiver', 'successfully delivered'])) {
            return ShipmentStatus::Delivered->value;
        }

        if (Str::contains($normalized, ['exception', 'undelivered', 'return', 'failed', 'delay', 'hold'])) {
            return ShipmentStatus::Exception->value;
        }

        if (Str::contains($normalized, ['transit', 'picked up', 'processed', 'arrived', 'departed', 'courier'])) {
            return ShipmentStatus::InTransit->value;
        }

        return null;
    }

    protected function parseEventTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function legacyParcelDimensions(?string $parcelType): array
    {
        return match (Str::lower((string) $parcelType)) {
            'mini' => ['length_cm' => 17, 'width_cm' => 45, 'height_cm' => 8],
            'small' => ['length_cm' => 17, 'width_cm' => 45, 'height_cm' => 20],
            'medium' => ['length_cm' => 36, 'width_cm' => 45, 'height_cm' => 20],
            'large' => ['length_cm' => 60, 'width_cm' => 45, 'height_cm' => 36],
            default => [],
        };
    }

    protected function positiveFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $numericValue = (float) $value;

        return $numericValue > 0 ? $numericValue : null;
    }

    protected function shipmentDescription(Order $order): string
    {
        $title = $order->items->pluck('title_snapshot')->filter()->first();

        return Str::limit((string) ($title ?: 'Cardora order'), 60, '');
    }
}
