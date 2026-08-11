<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
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
        $isCustomsDeclarable = $this->shipmentRequiresCustomsDeclaration($sender, $recipient);
        $productCode = $this->resolveProductCode($sender, $recipient, $isCustomsDeclarable);
        $normalizedSender = $this->normalizeDhlSender($sender);

        $plannedShipping = now()->addMinutes(15);
        $shipperPostalAddress = [
            'postalCode' => $normalizedSender['postal_code'],
            'cityName' => $normalizedSender['city'],
            'countryCode' => $normalizedSender['country_code'],
            'addressLine1' => $normalizedSender['address_line_1'],
        ];
        $receiverPostalAddress = [
            'postalCode' => $recipient['postal_code'],
            'cityName' => $recipient['city'],
            'countryCode' => $recipient['country_code'],
            'addressLine1' => $recipient['address_line_1'],
        ];

        if (filled($normalizedSender['address_line_2'] ?? null)) {
            $shipperPostalAddress['addressLine2'] = $normalizedSender['address_line_2'];
        }

        if (filled($recipient['address_line_2'] ?? null)) {
            $receiverPostalAddress['addressLine2'] = $recipient['address_line_2'];
        }

        $content = [
            'packages' => [
                [
                    'weight' => round((float) $package['weight_kg'], 2),
                    'dimensions' => [
                        'length' => $this->normalizeDhlDimensionCm($package['length_cm'] ?? null),
                        'width' => $this->normalizeDhlDimensionCm($package['width_cm'] ?? null),
                        'height' => $this->normalizeDhlDimensionCm($package['height_cm'] ?? null),
                    ],
                ],
            ],
            'isCustomsDeclarable' => $isCustomsDeclarable,
            'description' => $this->shipmentDescription($order),
            'declaredValue' => round((float) ($order->subtotal ?: $order->total_amount), 2),
            'declaredValueCurrency' => strtoupper((string) ($order->currency ?: 'EUR')),
            'unitOfMeasurement' => 'metric',
        ];

        if ($isCustomsDeclarable) {
            $content['incoterm'] = 'DAP';
            $content['exportDeclaration'] = $this->buildExportDeclaration($order, $sender, $recipient);
        }

        $payload = [
            // MyDHL API expects the "YYYY-MM-DDTHH:mm:ss GMT+hh:mm" pattern, not the ISO/atom format.
            'plannedShippingDateAndTime' => $plannedShipping->format('Y-m-d\TH:i:s').' GMT'.$plannedShipping->format('P'),
            'pickup' => [
                'isRequested' => false,
            ],
            'productCode' => $productCode,
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => (string) config('services.dhl.account_number'),
                ],
            ],
            'customerDetails' => [
                'shipperDetails' => [
                    'postalAddress' => $shipperPostalAddress,
                    'contactInformation' => [
                        'companyName' => $normalizedSender['company_name'] ?? $normalizedSender['full_name'],
                        'fullName' => $normalizedSender['full_name'],
                        'phone' => $normalizedSender['phone'],
                    ],
                    'registrationNumbers' => $this->buildSenderRegistrationNumbers(),
                    'typeCode' => 'business',
                ],
                'receiverDetails' => [
                    'postalAddress' => $receiverPostalAddress,
                    'contactInformation' => [
                        'companyName' => $recipient['company_name'] ?? $recipient['full_name'],
                        'fullName' => $recipient['full_name'],
                        'phone' => $recipient['phone'],
                    ],
                ],
            ],
            'content' => $content,
            'outputImageProperties' => $this->buildOutputImageProperties($isCustomsDeclarable),
        ];

        if ($isCustomsDeclarable) {
            $payload['valueAddedServices'] = [
                ['serviceCode' => 'WY'],
            ];
            $payload['documentImages'] = [
                [
                    'typeCode' => 'INV',
                    'imageFormat' => 'PDF',
                    'content' => base64_encode($this->buildCommercialInvoicePdf($order, $normalizedSender, $recipient)),
                ],
            ];
        }

        return $payload;
    }

    protected function normalizeDhlDimensionCm(mixed $value): int
    {
        $numericValue = max((float) $value, 1.0);

        return max((int) ceil($numericValue), 1);
    }

    protected function resolveProductCode(array $sender, array $recipient, bool $isCustomsDeclarable): string
    {
        if (($sender['country_code'] ?? '') === 'GR' && ($recipient['country_code'] ?? '') === 'GR') {
            return (string) config('services.dhl.domestic_product_code', 'N');
        }

        if (! $isCustomsDeclarable && $this->isEuropeanCountryCode((string) ($recipient['country_code'] ?? ''))) {
            return (string) config('services.dhl.intra_eu_product_code', 'U');
        }

        if ($isCustomsDeclarable) {
            return (string) config('services.dhl.international_product_code', 'P');
        }

        return (string) config('services.dhl.default_product_code', 'N');
    }

    protected function normalizeDhlSender(array $sender): array
    {
        $normalized = $sender;

        if (($normalized['country_code'] ?? '') !== 'GR') {
            return $normalized;
        }

        $fullName = trim((string) ($normalized['full_name'] ?? ''));
        $legalName = trim((string) ($normalized['legal_name'] ?? ''));
        $displayName = trim((string) ($normalized['display_name'] ?? ''));
        $handle = trim((string) ($normalized['handle'] ?? ''));

        if (
            $legalName !== ''
            && (
                Str::contains(Str::lower($fullName), ['test sender', 'test'])
                || ($displayName !== '' && Str::lower($fullName) === Str::lower($displayName))
                || ($handle !== '' && Str::lower($fullName) === Str::lower($handle))
            )
        ) {
            $normalized['full_name'] = $legalName;
        }

        $displayCity = trim((string) ($normalized['city'] ?? ''));
        $normalized['city'] = (string) config('services.dhl.sender_city_name', 'AMBELOKIPI');

        if ($displayCity !== '' && Str::upper($displayCity) !== Str::upper($normalized['city'])) {
            $normalized['address_line_2'] = collect([
                $normalized['address_line_2'] ?? null,
                $displayCity,
            ])->filter(fn ($value) => filled($value))->implode(', ');
        }

        return $normalized;
    }

    protected function buildSenderRegistrationNumbers(): array
    {
        $registrationNumbers = [];
        $vatNumber = trim((string) config('services.dhl.sender_vat_number', ''));
        $vatIssuerCountryCode = Str::upper((string) config('services.dhl.sender_vat_country_code', 'GR'));

        if ($vatNumber !== '') {
            $registrationNumbers[] = [
                'typeCode' => 'VAT',
                'number' => $vatNumber,
                'issuerCountryCode' => $vatIssuerCountryCode,
            ];
        }

        return $registrationNumbers;
    }

    protected function shipmentRequiresCustomsDeclaration(array $sender, array $recipient): bool
    {
        return ($sender['country_code'] ?? '') !== ($recipient['country_code'] ?? '')
            && ! $this->isEuropeanCountryCode((string) ($recipient['country_code'] ?? ''));
    }

    protected function isEuropeanCountryCode(string $countryCode): bool
    {
        return in_array(Str::upper($countryCode), [
            'AL', 'AD', 'AT', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO',
            'FR', 'GI', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MC', 'MD', 'ME', 'MK',
            'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'SE', 'SI', 'SK', 'SM', 'UA', 'VA',
        ], true);
    }

    protected function buildOutputImageProperties(bool $isCustomsDeclarable): array
    {
        $imageOptions = [
            [
                'typeCode' => 'label',
                'templateName' => 'ECOM26_84_A4_001',
            ],
        ];

        if ($isCustomsDeclarable) {
            $imageOptions[] = [
                'invoiceType' => 'commercial',
                'isRequested' => true,
                'typeCode' => 'invoice',
            ];
            $imageOptions[] = [
                'hideAccountNumber' => false,
                'isRequested' => true,
                'typeCode' => 'waybillDoc',
            ];
        }

        return [
            'printerDPI' => 300,
            'encodingFormat' => 'pdf',
            'imageOptions' => $imageOptions,
        ];
    }

    protected function buildExportDeclaration(Order $order, array $sender, array $recipient): array
    {
        return [
            'invoice' => [
                'number' => sprintf('CI-%s', $order->order_number),
                'date' => Carbon::now()->toDateString(),
            ],
            'exportReason' => 'COMMERCIAL',
            'exportReasonType' => 'permanent',
            'lineItems' => $order->items
                ->map(fn (OrderItem $item, int $index) => $this->buildExportLineItem($item, $index + 1, $sender, $recipient))
                ->values()
                ->all(),
        ];
    }

    protected function buildExportLineItem(OrderItem $item, int $number, array $sender, array $recipient): array
    {
        $quantity = max((int) ($item->quantity ?? 1), 1);
        $unitPrice = round((float) ($item->unit_price ?? 0), 2);
        $weight = round(max((float) data_get($item->listing?->attributes, 'shipping.package.weight_kg', 0.5), 0.1), 2);
        $commodityCode = $this->resolveCommodityCode($item);

        return [
            'number' => $number,
            'description' => $this->buildDetailedItemDescription($item),
            'price' => $unitPrice,
            'quantity' => [
                'value' => $quantity,
                'unitOfMeasurement' => 'PCS',
            ],
            'manufacturerCountry' => Str::upper((string) ($sender['country_code'] ?? 'GR')),
            'commodityCodes' => [
                [
                    'value' => $commodityCode,
                    'typeCode' => 'outbound',
                ],
                [
                    'value' => $commodityCode,
                    'typeCode' => 'inbound',
                ],
            ],
            'weight' => [
                'netValue' => $weight,
                'grossValue' => $weight,
            ],
        ];
    }

    protected function resolveCommodityCode(OrderItem $item): string
    {
        $listingAttributes = is_array($item->listing?->attributes) ? $item->listing->attributes : [];
        $productMetadata = is_array($item->product?->metadata) ? $item->product->metadata : [];

        $explicitCode = data_get($listingAttributes, 'shipping.customs.commodity_code')
            ?? data_get($listingAttributes, 'shipping.customs.hs_code')
            ?? data_get($productMetadata, 'customs.commodity_code')
            ?? data_get($productMetadata, 'customs.hs_code');

        if (is_string($explicitCode) && trim($explicitCode) !== '') {
            return preg_replace('/\s+/', '', trim($explicitCode)) ?: '95044000';
        }

        $haystack = Str::lower(implode(' ', array_filter([
            $item->title_snapshot,
            $item->product?->title,
            $item->product?->product_type,
            $item->product?->category?->name,
            $item->listing?->category?->name,
        ])));

        return match (true) {
            Str::contains($haystack, ['comic', 'manga', 'book', 'graphic novel']) => '49019900',
            Str::contains($haystack, ['figure', 'statue', 'funko', 'collectible toy']) => '95030000',
            default => '95044000',
        };
    }

    protected function buildDetailedItemDescription(OrderItem $item): string
    {
        $title = trim((string) ($item->title_snapshot ?: $item->product?->title ?: ''));
        $descriptor = Str::lower(implode(' ', array_filter([
            $item->product?->product_type,
            $item->product?->category?->name,
            $item->listing?->category?->name,
            $title,
        ])));

        $baseDescription = match (true) {
            Str::contains($descriptor, ['graded', 'slab']) => 'Graded printed cardboard collectible trading card',
            Str::contains($descriptor, ['card', 'trading']) => 'Printed cardboard collectible trading card',
            Str::contains($descriptor, ['comic', 'manga', 'book']) => 'Printed collectible comic book',
            Str::contains($descriptor, ['figure', 'statue', 'funko']) => 'Vinyl or resin collector display figure',
            default => 'Collectible merchandise item',
        };

        if ($title === '') {
            return $baseDescription;
        }

        return Str::limit(sprintf('%s - %s', $baseDescription, $title), 70, '');
    }

    protected function buildCommercialInvoicePdf(Order $order, array $sender, array $recipient): string
    {
        $lines = [
            'COMMERCIAL INVOICE',
            sprintf('Invoice No: CI-%s', $order->order_number),
            sprintf('Invoice Date: %s', Carbon::now()->toDateString()),
            '',
            sprintf('Shipper: %s', $this->asciiPdfText((string) ($sender['full_name'] ?? 'Cardora Seller'))),
            sprintf('Shipper City: %s', $this->asciiPdfText((string) ($sender['city'] ?? 'AMBELOKIPI'))),
            sprintf('Receiver: %s', $this->asciiPdfText((string) ($recipient['full_name'] ?? 'Receiver'))),
            sprintf('Receiver Country: %s', $this->asciiPdfText((string) ($recipient['country_code'] ?? ''))),
            '',
        ];

        foreach ($order->items as $index => $item) {
            $lines[] = sprintf(
                'Item %d: %s | Qty %d | Unit EUR %.2f | HS %s',
                $index + 1,
                $this->asciiPdfText($this->buildDetailedItemDescription($item)),
                max((int) ($item->quantity ?? 1), 1),
                round((float) ($item->unit_price ?? 0), 2),
                $this->resolveCommodityCode($item)
            );
        }

        $lines[] = '';
        $lines[] = sprintf('Subtotal EUR %.2f', round((float) ($order->subtotal ?? 0), 2));
        $lines[] = sprintf('Declared Value EUR %.2f', round((float) ($order->subtotal ?: $order->total_amount), 2));
        $lines[] = 'Export Reason: COMMERCIAL';
        $lines[] = 'Export Reason Type: permanent';

        return $this->buildSimplePdf($lines);
    }

    protected function buildSimplePdf(array $lines): string
    {
        $y = 780;
        $content = "BT\n/F1 11 Tf\n50 {$y} Td\n14 TL\n";

        foreach ($lines as $index => $line) {
            $content .= sprintf('(%s) Tj', $this->escapePdfText($line));
            $content .= $index === array_key_last($lines) ? "\n" : "\nT*\n";
        }

        $content .= "ET";

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n";
        $objects[] = sprintf("4 0 obj\n<< /Length %d >>\nstream\n%s\nendstream\nendobj\n", strlen($content), $content);
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    protected function asciiPdfText(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $normalized === false ? preg_replace('/[^\x20-\x7E]/', '', $value) ?: '' : $normalized;
    }

    protected function escapePdfText(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $this->asciiPdfText($value)
        );
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
