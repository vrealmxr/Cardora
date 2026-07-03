<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profileMap = [
            'boxnow_domestic_only' => 'dhl_domestic_only',
            'boxnow_domestic_dhl_international' => 'dhl_domestic_dhl_international',
            'calculated_domestic_only' => 'dhl_domestic_only',
            'calculated_domestic_dhl_international' => 'dhl_domestic_dhl_international',
        ];
        $defaultDhlFee = 7.04;

        DB::table('listings')
            ->where(function ($query) use ($profileMap): void {
                $query->whereIn('shipping_profile', array_keys($profileMap))
                    ->orWhere('shipping_methods', 'like', '%BoxNow%')
                    ->orWhere('shipping_methods', 'like', '%boxnow%');
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($defaultDhlFee, $profileMap): void {
                foreach ($rows as $row) {
                    $attributes = $this->decodeJsonObject($row->attributes);
                    $domesticFee = $this->extractDomesticFee($attributes);
                    $includedInPrice = $this->truthy($this->attributeValue($attributes, 'shipping.domestic.included_in_price'));
                    $shippingCost = (float) ($row->shipping_cost ?? 0);
                    $normalizedDomesticFee = $domesticFee > 0 ? $domesticFee : ($shippingCost > 0 ? round($shippingCost, 2) : $defaultDhlFee);

                    $update = [
                        'shipping_profile' => $profileMap[$row->shipping_profile] ?? 'dhl_domestic_only',
                        'shipping_methods' => $this->encodeJson(['DHL Express']),
                        'attributes' => $this->encodeJson(
                            $this->normalizeAttributesForDhl($attributes, $normalizedDomesticFee, $includedInPrice)
                        ),
                        'updated_at' => now(),
                    ];

                    if ($normalizedDomesticFee > 0) {
                        $update['shipping_cost'] = $normalizedDomesticFee;
                    }

                    if ($includedInPrice && $normalizedDomesticFee > 0) {
                        foreach ([
                            'price',
                            'old_price',
                            'minimum_offer',
                            'starting_bid',
                            'current_bid',
                            'reserve_price',
                            'buyout_price',
                        ] as $column) {
                            if ($row->{$column} === null) {
                                continue;
                            }

                            $update[$column] = round(max(((float) $row->{$column}) - $normalizedDomesticFee, 0), 2);
                        }
                    }

                    DB::table('listings')
                        ->where('id', $row->id)
                        ->update($update);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data migration: legacy BoxNow/calculated profiles were normalized to DHL.
    }

    protected function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function extractDomesticFee(array $attributes): float
    {
        $candidates = [
            $this->attributeValue($attributes, 'shipping.domestic.fee'),
            $this->attributeValue($attributes, 'shipping.domestic.rates.gr'),
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (float) $candidate >= 0) {
                return round((float) $candidate, 2);
            }
        }

        return 0.0;
    }

    protected function normalizeAttributesForDhl(array $attributes, float $domesticFee, bool $includedInPrice): array
    {
        $this->setAttributeValue($attributes, 'shipping.domestic.carrier', 'DHL Express');

        if ($domesticFee > 0) {
            $this->setAttributeValue($attributes, 'shipping.domestic.fee', round($domesticFee, 2));
        }

        $this->setAttributeValue($attributes, 'shipping.domestic.included_in_price', false);
        $this->setAttributeValue($attributes, 'shipping.domestic.package.weight_kg', $this->attributeValue($attributes, 'shipping.domestic.package.weight_kg') ?? 0.5);
        $this->setAttributeValue($attributes, 'shipping.domestic.package.length_cm', $this->attributeValue($attributes, 'shipping.domestic.package.length_cm') ?? 20);
        $this->setAttributeValue($attributes, 'shipping.domestic.package.width_cm', $this->attributeValue($attributes, 'shipping.domestic.package.width_cm') ?? 15);
        $this->setAttributeValue($attributes, 'shipping.domestic.package.height_cm', $this->attributeValue($attributes, 'shipping.domestic.package.height_cm') ?? 8);
        $this->setAttributeValue($attributes, 'shipping.package.weight_kg', $this->attributeValue($attributes, 'shipping.package.weight_kg') ?? 0.5);
        $this->setAttributeValue($attributes, 'shipping.package.length_cm', $this->attributeValue($attributes, 'shipping.package.length_cm') ?? 20);
        $this->setAttributeValue($attributes, 'shipping.package.width_cm', $this->attributeValue($attributes, 'shipping.package.width_cm') ?? 15);
        $this->setAttributeValue($attributes, 'shipping.package.height_cm', $this->attributeValue($attributes, 'shipping.package.height_cm') ?? 8);

        $this->unsetAttributeValue($attributes, 'shipping.domestic.parcel_type');
        $this->unsetAttributeValue($attributes, 'shipping.domestic.parcelType');
        $this->unsetAttributeValue($attributes, 'shipping.domestic.rates.gr');
        $this->unsetAttributeValue($attributes, 'shipping.domestic.rates.cy');

        if ($includedInPrice) {
            $this->setAttributeValue($attributes, 'shipping.migration.domestic_fee_moved_from_price', true);
        }

        return $attributes;
    }

    protected function attributeValue(array $attributes, string $path): mixed
    {
        return $attributes[$path] ?? data_get($attributes, $path);
    }

    protected function setAttributeValue(array &$attributes, string $path, mixed $value): void
    {
        $attributes[$path] = $value;
        data_set($attributes, $path, $value);
    }

    protected function unsetAttributeValue(array &$attributes, string $path): void
    {
        unset($attributes[$path]);

        $segments = explode('.', $path);
        $last = array_pop($segments);
        $target = &$attributes;

        foreach ($segments as $segment) {
            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        if (is_array($target) && array_key_exists($last, $target)) {
            unset($target[$last]);
        }
    }

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
};
