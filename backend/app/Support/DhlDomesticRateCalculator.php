<?php

namespace App\Support;

class DhlDomesticRateCalculator
{
    private const VOLUMETRIC_DIVISOR = 5000.0;
    private const BASE_RATE_UP_TO_1KG = 7.04;
    private const HALF_KG_STEP_FEE = 1.69;
    private const ONE_KG_STEP_FEE = 3.38;
    private const BASE_RATE_AT_30KG = 105.03;
    private const OVERSIZE_FEE = 12.00;
    private const NON_CONVEYABLE_WEIGHT_FEE = 12.00;
    private const OVERWEIGHT_FEE = 50.00;
    private const MAX_LONGEST_SIDE_CM = 100.0;
    private const MAX_SECOND_LONGEST_SIDE_CM = 80.0;

    public static function calculate(float $weightKg, float $lengthCm, float $widthCm, float $heightCm): float
    {
        return self::breakdown($weightKg, $lengthCm, $widthCm, $heightCm)['total_fee'];
    }

    public static function breakdown(float $weightKg, float $lengthCm, float $widthCm, float $heightCm): array
    {
        $actualWeightKg = max($weightKg, 0.5);
        $volumetricWeightKg = max($lengthCm, 1.0) * max($widthCm, 1.0) * max($heightCm, 1.0) / self::VOLUMETRIC_DIVISOR;
        $billableWeightKg = max($actualWeightKg, $volumetricWeightKg);
        $baseFee = self::baseFee($billableWeightKg);
        $surcharges = self::surcharges($actualWeightKg, $billableWeightKg, $lengthCm, $widthCm, $heightCm);
        $surchargeTotal = array_reduce(
            $surcharges,
            fn (float $total, array $entry): float => $total + (float) $entry['fee'],
            0.0
        );

        return [
            'actual_weight_kg' => round($actualWeightKg, 2),
            'volumetric_weight_kg' => round($volumetricWeightKg, 2),
            'billable_weight_kg' => round($billableWeightKg, 2),
            'base_fee' => round($baseFee, 2),
            'surcharges' => $surcharges,
            'surcharge_total' => round($surchargeTotal, 2),
            'total_fee' => round($baseFee + $surchargeTotal, 2),
        ];
    }

    private static function baseFee(float $billableWeightKg): float
    {
        if ($billableWeightKg <= 1.0) {
            return self::BASE_RATE_UP_TO_1KG;
        }

        if ($billableWeightKg <= 30.0) {
            $ratedWeightKg = self::roundWeightUp($billableWeightKg - 1.0, 0.5);

            return self::BASE_RATE_UP_TO_1KG + ($ratedWeightKg / 0.5) * self::HALF_KG_STEP_FEE;
        }

        $ratedWeightKg = self::roundWeightUp($billableWeightKg - 30.0, 1.0);

        return self::BASE_RATE_AT_30KG + $ratedWeightKg * self::ONE_KG_STEP_FEE;
    }

    private static function surcharges(float $actualWeightKg, float $billableWeightKg, float $lengthCm, float $widthCm, float $heightCm): array
    {
        $surcharges = [];
        $sortedSides = [max($lengthCm, 1.0), max($widthCm, 1.0), max($heightCm, 1.0)];
        rsort($sortedSides, SORT_NUMERIC);

        $isOversize = $sortedSides[0] > self::MAX_LONGEST_SIDE_CM || $sortedSides[1] > self::MAX_SECOND_LONGEST_SIDE_CM;

        if ($isOversize) {
            $surcharges[] = [
                'code' => 'oversize_piece',
                'fee' => self::OVERSIZE_FEE,
            ];
        }

        if ($billableWeightKg > 70.0) {
            $surcharges[] = [
                'code' => 'overweight_piece',
                'fee' => self::OVERWEIGHT_FEE,
            ];
        } elseif ($actualWeightKg >= 25.0 && $actualWeightKg <= 70.0 && ! $isOversize) {
            $surcharges[] = [
                'code' => 'non_conveyable_weight',
                'fee' => self::NON_CONVEYABLE_WEIGHT_FEE,
            ];
        }

        return array_map(
            fn (array $entry): array => [
                'code' => $entry['code'],
                'fee' => round((float) $entry['fee'], 2),
            ],
            $surcharges
        );
    }

    private static function roundWeightUp(float $weightKg, float $stepKg): float
    {
        return ceil(($weightKg + PHP_FLOAT_EPSILON) / $stepKg) * $stepKg;
    }
}
