<?php

namespace App\Support;

class MarketplaceSellerFeeCalculator
{
    public const FIXED_FEE_AMOUNT = 1.00;
    public const FIXED_FEE_MAX_ITEM_AMOUNT = 5.00;
    public const MID_TIER_MAX_ITEM_AMOUNT = 300.00;
    public const UPPER_TIER_MAX_ITEM_AMOUNT = 2000.00;
    public const MID_TIER_RATE = 0.065;
    public const UPPER_TIER_RATE = 0.05;
    public const PREMIUM_TIER_RATE = 0.04;
    public const MAX_FEE_CAP = 400.00;

    public static function calculate(float $itemAmount): float
    {
        $amount = round(max(0.0, $itemAmount), 2);

        if ($amount <= 0.0) {
            return 0.0;
        }

        if ($amount <= self::FIXED_FEE_MAX_ITEM_AMOUNT) {
            return self::FIXED_FEE_AMOUNT;
        }

        if ($amount <= self::MID_TIER_MAX_ITEM_AMOUNT) {
            return self::applyCap($amount * self::MID_TIER_RATE);
        }

        if ($amount <= self::UPPER_TIER_MAX_ITEM_AMOUNT) {
            return self::applyCap($amount * self::UPPER_TIER_RATE);
        }

        return self::applyCap($amount * self::PREMIUM_TIER_RATE);
    }

    public static function effectiveRate(float $itemAmount): float
    {
        $amount = round(max(0.0, $itemAmount), 2);

        if ($amount <= 0.0) {
            return 0.0;
        }

        $commission = self::calculate($amount);

        if ($commission <= 0.0) {
            return 0.0;
        }

        return round($commission / $amount, 6);
    }

    protected static function applyCap(float $value): float
    {
        return round(min(max(0.0, $value), self::MAX_FEE_CAP), 2);
    }
}
