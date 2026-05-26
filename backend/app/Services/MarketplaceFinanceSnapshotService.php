<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class MarketplaceFinanceSnapshotService
{
    public function snapshot(): array
    {
        return Cache::remember('filament:marketplace-finance-snapshot', now()->addMinutes(5), function (): array {
            $capturedStatuses = [
                OrderStatus::PaidPendingRelease->value,
                OrderStatus::Released->value,
            ];

            $orders = Order::query()
                ->whereIn('status', $capturedStatuses)
                ->whereNotNull('stripe_charge_id')
                ->get([
                    'id',
                    'status',
                    'total_amount',
                    'commission_amount',
                    'seller_amount',
                    'stripe_charge_id',
                ]);

            $grossCommission = (float) $orders->sum('commission_amount');
            $heldFunds = (float) $orders
                ->where('status', OrderStatus::PaidPendingRelease->value)
                ->sum('seller_amount');
            $releasedToSellers = (float) $orders
                ->where('status', OrderStatus::Released->value)
                ->sum('seller_amount');

            [$stripeFees, $feesSynced] = $this->resolveStripeFees($orders->pluck('stripe_charge_id')->filter()->unique()->values()->all());

            return [
                'gross_commission' => round($grossCommission, 2),
                'stripe_fees' => round($stripeFees, 2),
                'net_revenue' => round($grossCommission - $stripeFees, 2),
                'held_funds' => round($heldFunds, 2),
                'released_to_sellers' => round($releasedToSellers, 2),
                'orders_count' => $orders->count(),
                'fees_synced' => $feesSynced,
            ];
        });
    }

    protected function resolveStripeFees(array $chargeIds): array
    {
        if ($chargeIds === [] || blank(config('services.stripe.secret'))) {
            return [0.0, false];
        }

        $stripe = new StripeClient((string) config('services.stripe.secret'));
        $fees = 0.0;

        try {
            foreach ($chargeIds as $chargeId) {
                $charge = $stripe->charges->retrieve((string) $chargeId, []);
                $balanceTransactionId = $charge->balance_transaction ?? null;

                if (! $balanceTransactionId) {
                    continue;
                }

                $balanceTransaction = $stripe->balanceTransactions->retrieve((string) $balanceTransactionId, []);
                $fees += ((int) ($balanceTransaction->fee ?? 0)) / 100;
            }

            return [$fees, true];
        } catch (ApiErrorException $exception) {
            report($exception);

            return [0.0, false];
        }
    }
}
