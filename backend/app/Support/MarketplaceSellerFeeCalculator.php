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

    // Cardora PRO rates — same tier breakpoints and fee cap, ~20-25% lower.
    public const PRO_FIXED_FEE_AMOUNT = 0.75;
    public const PRO_MID_TIER_RATE = 0.05;
    public const PRO_UPPER_TIER_RATE = 0.04;
    public const PRO_PREMIUM_TIER_RATE = 0.03;

    public static function calculate(float $itemAmount, bool $isPro = false): float
    {
        $amount = round(max(0.0, $itemAmount), 2);

        if ($amount <= 0.0) {
            return 0.0;
        }

        if ($amount <= self::FIXED_FEE_MAX_ITEM_AMOUNT) {
            return $isPro ? self::PRO_FIXED_FEE_AMOUNT : self::FIXED_FEE_AMOUNT;
        }

        if ($amount <= self::MID_TIER_MAX_ITEM_AMOUNT) {
            return self::applyCap($amount * ($isPro ? self::PRO_MID_TIER_RATE : self::MID_TIER_RATE));
        }

        if ($amount <= self::UPPER_TIER_MAX_ITEM_AMOUNT) {
            return self::applyCap($amount * ($isPro ? self::PRO_UPPER_TIER_RATE : self::UPPER_TIER_RATE));
        }

        return self::applyCap($amount * ($isPro ? self::PRO_PREMIUM_TIER_RATE : self::PREMIUM_TIER_RATE));
    }

    /**
     * True for the flat-fee tier (item price <= €5), where the fee is a fixed
     * amount that can exceed the item's own price. Callers must add this fee
     * to what the buyer pays rather than deduct it from the seller's share —
     * otherwise the seller ends up with a negative payout.
     */
    public static function isFixedFeeTier(float $itemAmount): bool
    {
        $amount = round(max(0.0, $itemAmount), 2);

        return $amount > 0.0 && $amount <= self::FIXED_FEE_MAX_ITEM_AMOUNT;
    }

    public static function effectiveRate(float $itemAmount, bool $isPro = false): float
    {
        $amount = round(max(0.0, $itemAmount), 2);

        if ($amount <= 0.0) {
            return 0.0;
        }

        $commission = self::calculate($amount, $isPro);

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
