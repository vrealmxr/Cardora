<?php

namespace App\Services;

use App\Enums\BalanceTransactionStatus;
use App\Enums\BalanceTransactionType;
use App\Enums\PayoutStatus;
use App\Models\BalanceTransaction;
use App\Models\Order;
use App\Models\Payout;
use App\Models\SellerBalance;
use App\Models\SellerPayoutAccount;
use App\Models\User;
use App\Support\MarketplaceSellerFeeCalculator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SellerLedgerService
{
    public function getOrCreateBalance(int|User $seller, string $currency = 'EUR'): SellerBalance
    {
        $sellerId = $seller instanceof User ? $seller->getKey() : $seller;
        $currency = strtoupper($currency);

        return SellerBalance::firstOrCreate(
            [
                'seller_id' => $sellerId,
                'currency' => $currency,
            ],
            [
                'pending_amount' => 0,
                'available_amount' => 0,
                'paid_out_amount' => 0,
            ]
        );
    }

    public function summaryForSeller(User $seller, string $currency = 'EUR'): array
    {
        $balance = $this->getOrCreateBalance($seller, $currency);

        return [
            'currency' => strtoupper($balance->currency),
            'pending_amount' => (float) $balance->pending_amount,
            'available_amount' => (float) $balance->available_amount,
            'paid_out_amount' => (float) $balance->paid_out_amount,
            'total_released' => (float) BalanceTransaction::query()
                ->where('seller_id', $seller->getKey())
                ->where('currency', $balance->currency)
                ->where('type', BalanceTransactionType::ReleaseToAvailable->value)
                ->where('status', BalanceTransactionStatus::Completed->value)
                ->sum('amount'),
            'recent_transactions' => BalanceTransaction::query()
                ->where('seller_id', $seller->getKey())
                ->where('currency', $balance->currency)
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BalanceTransaction $transaction) => [
                    'id' => $transaction->getKey(),
                    'order_id' => $transaction->order_id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'description' => $transaction->description,
                    'metadata' => $transaction->metadata ?? [],
                    'created_at' => optional($transaction->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    public function recordPaymentCaptured(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $balance = $this->lockBalance($order->seller_id, $order->currency);
            $sellerAmount = (float) $order->seller_amount;
            $commissionAmount = (float) $order->commission_amount;
            $subtotal = (float) $order->subtotal;

            $balance->update([
                'pending_amount' => round(((float) $balance->pending_amount) + $sellerAmount, 2),
            ]);

            BalanceTransaction::updateOrCreate(
                [
                    'seller_id' => $order->seller_id,
                    'order_id' => $order->getKey(),
                    'type' => BalanceTransactionType::PendingCredit->value,
                ],
                [
                    'amount' => $sellerAmount,
                    'currency' => strtoupper($order->currency),
                    'status' => BalanceTransactionStatus::Completed->value,
                    'description' => sprintf('Pending escrow credit for order %s', $order->order_number),
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                    ],
                ]
            );

            BalanceTransaction::updateOrCreate(
                [
                    'seller_id' => $order->seller_id,
                    'order_id' => $order->getKey(),
                    'type' => BalanceTransactionType::Commission->value,
                ],
                [
                    'amount' => -abs($commissionAmount),
                    'currency' => strtoupper($order->currency),
                    'status' => BalanceTransactionStatus::Completed->value,
                    'description' => sprintf('Cardora commission for order %s', $order->order_number),
                    'metadata' => [
                        'commission_rate' => MarketplaceSellerFeeCalculator::effectiveRate($subtotal),
                        'commission_model' => 'tiered_seller_fee_v2',
                        'commission_cap' => MarketplaceSellerFeeCalculator::MAX_FEE_CAP,
                    ],
                ]
            );
        });
    }

    public function recordFundsRelease(Order $order, string $stripeTransferId): void
    {
        DB::transaction(function () use ($order, $stripeTransferId) {
            $balance = $this->lockBalance($order->seller_id, $order->currency);
            $sellerAmount = (float) $order->seller_amount;

            $balance->update([
                'pending_amount' => round(max(0, ((float) $balance->pending_amount) - $sellerAmount), 2),
                'available_amount' => round(((float) $balance->available_amount) + $sellerAmount, 2),
            ]);

            BalanceTransaction::updateOrCreate(
                [
                    'seller_id' => $order->seller_id,
                    'order_id' => $order->getKey(),
                    'type' => BalanceTransactionType::ReleaseToAvailable->value,
                ],
                [
                    'amount' => $sellerAmount,
                    'currency' => strtoupper($order->currency),
                    'status' => BalanceTransactionStatus::Completed->value,
                    'description' => sprintf('Funds released for order %s', $order->order_number),
                    'metadata' => [
                        'released_at' => optional($order->released_at)->toIso8601String(),
                    ],
                ]
            );

            BalanceTransaction::updateOrCreate(
                [
                    'seller_id' => $order->seller_id,
                    'order_id' => $order->getKey(),
                    'type' => BalanceTransactionType::TransferToConnectedAccount->value,
                ],
                [
                    'amount' => $sellerAmount,
                    'currency' => strtoupper($order->currency),
                    'status' => BalanceTransactionStatus::Completed->value,
                    'description' => sprintf('Stripe transfer to connected account for order %s', $order->order_number),
                    'metadata' => [
                        'stripe_transfer_id' => $stripeTransferId,
                        'stripe_account_id' => optional($order->seller->sellerPayoutAccount)->stripe_account_id,
                    ],
                ]
            );
        });
    }

    public function reversePendingFundsForRefund(Order $order, array $metadata = []): void
    {
        DB::transaction(function () use ($order, $metadata) {
            $balance = $this->lockBalance($order->seller_id, $order->currency);
            $sellerAmount = (float) $order->seller_amount;

            $balance->update([
                'pending_amount' => round(max(0, ((float) $balance->pending_amount) - $sellerAmount), 2),
            ]);

            BalanceTransaction::updateOrCreate(
                [
                    'seller_id' => $order->seller_id,
                    'order_id' => $order->getKey(),
                    'type' => BalanceTransactionType::Refund->value,
                ],
                [
                    'amount' => -abs($sellerAmount),
                    'currency' => strtoupper($order->currency),
                    'status' => BalanceTransactionStatus::Completed->value,
                    'description' => sprintf('Refund before release for order %s', $order->order_number),
                    'metadata' => $metadata,
                ]
            );
        });
    }

    public function recordPostReleaseRefundAlert(Order $order, array $metadata = []): void
    {
        BalanceTransaction::updateOrCreate(
            [
                'seller_id' => $order->seller_id,
                'order_id' => $order->getKey(),
                'type' => BalanceTransactionType::Refund->value,
                'status' => BalanceTransactionStatus::Pending->value,
            ],
            [
                'amount' => -abs((float) $order->seller_amount),
                'currency' => strtoupper($order->currency),
                'description' => sprintf('Refund requested after release for order %s', $order->order_number),
                'metadata' => array_merge($metadata, [
                    'requires_manual_recovery' => true,
                ]),
            ]
        );
    }

    public function markPayoutPaid(SellerPayoutAccount $account, array $payload): Payout
    {
        return DB::transaction(function () use ($account, $payload) {
            $currency = strtoupper((string) ($payload['currency'] ?? $account->default_currency ?? 'EUR'));
            $amount = $this->stripeAmountToDecimal((int) ($payload['amount'] ?? 0));
            $balance = $this->lockBalance($account->seller_id, $currency);

            $balance->update([
                'available_amount' => round(max(0, ((float) $balance->available_amount) - $amount), 2),
                'paid_out_amount' => round(((float) $balance->paid_out_amount) + $amount, 2),
            ]);

            $payout = Payout::updateOrCreate(
                [
                    'stripe_payout_id' => (string) $payload['stripe_payout_id'],
                ],
                [
                    'user_id' => $account->seller_id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => PayoutStatus::Paid->value,
                    'destination' => Arr::get($payload, 'destination'),
                    'connected_account_id' => $account->stripe_account_id,
                    'requested_at' => Arr::get($payload, 'requested_at') ? Carbon::createFromTimestamp((int) $payload['requested_at']) : now(),
                    'processed_at' => Arr::get($payload, 'processed_at') ? Carbon::createFromTimestamp((int) $payload['processed_at']) : now(),
                    'arrival_date' => Arr::get($payload, 'arrival_date') ? Carbon::createFromTimestamp((int) $payload['arrival_date']) : null,
                    'metadata' => array_merge($payload['metadata'] ?? [], [
                        'stripe_payout_id' => $payload['stripe_payout_id'],
                    ]),
                ]
            );

            BalanceTransaction::updateOrCreate(
                [
                    'seller_id' => $account->seller_id,
                    'order_id' => null,
                    'type' => BalanceTransactionType::Payout->value,
                    'description' => sprintf('Stripe payout %s', $payload['stripe_payout_id']),
                ],
                [
                    'amount' => -abs($amount),
                    'currency' => $currency,
                    'status' => BalanceTransactionStatus::Completed->value,
                    'metadata' => [
                        'stripe_payout_id' => $payload['stripe_payout_id'],
                        'connected_account_id' => $account->stripe_account_id,
                    ],
                ]
            );

            return $payout;
        });
    }

    public function markPayoutFailed(SellerPayoutAccount $account, array $payload): Payout
    {
        return DB::transaction(function () use ($account, $payload) {
            $currency = strtoupper((string) ($payload['currency'] ?? $account->default_currency ?? 'EUR'));
            $amount = $this->stripeAmountToDecimal((int) ($payload['amount'] ?? 0));
            $balance = $this->lockBalance($account->seller_id, $currency);
            $existingPayout = Payout::query()
                ->where('stripe_payout_id', (string) $payload['stripe_payout_id'])
                ->first();

            if ($existingPayout && $existingPayout->status === PayoutStatus::Paid->value) {
                $balance->update([
                    'available_amount' => round(((float) $balance->available_amount) + $amount, 2),
                    'paid_out_amount' => round(max(0, ((float) $balance->paid_out_amount) - $amount), 2),
                ]);
            }

            $payout = Payout::updateOrCreate(
                [
                    'stripe_payout_id' => (string) $payload['stripe_payout_id'],
                ],
                [
                    'user_id' => $account->seller_id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => PayoutStatus::Failed->value,
                    'destination' => Arr::get($payload, 'destination'),
                    'connected_account_id' => $account->stripe_account_id,
                    'requested_at' => Arr::get($payload, 'requested_at') ? Carbon::createFromTimestamp((int) $payload['requested_at']) : now(),
                    'processed_at' => now(),
                    'arrival_date' => Arr::get($payload, 'arrival_date') ? Carbon::createFromTimestamp((int) $payload['arrival_date']) : null,
                    'metadata' => array_merge($payload['metadata'] ?? [], [
                        'stripe_payout_id' => $payload['stripe_payout_id'],
                        'failure_code' => Arr::get($payload, 'failure_code'),
                        'failure_message' => Arr::get($payload, 'failure_message'),
                    ]),
                ]
            );

            BalanceTransaction::updateOrCreate(
                [
                    'seller_id' => $account->seller_id,
                    'order_id' => null,
                    'type' => BalanceTransactionType::Payout->value,
                    'description' => sprintf('Stripe payout %s', $payload['stripe_payout_id']),
                ],
                [
                    'amount' => -abs($amount),
                    'currency' => $currency,
                    'status' => BalanceTransactionStatus::Failed->value,
                    'metadata' => [
                        'stripe_payout_id' => $payload['stripe_payout_id'],
                        'connected_account_id' => $account->stripe_account_id,
                        'failure_code' => Arr::get($payload, 'failure_code'),
                        'failure_message' => Arr::get($payload, 'failure_message'),
                    ],
                ]
            );

            return $payout;
        });
    }

    protected function lockBalance(int $sellerId, string $currency): SellerBalance
    {
        $currency = strtoupper($currency);

        $existing = SellerBalance::query()
            ->where('seller_id', $sellerId)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        return $existing ?: $this->getOrCreateBalance($sellerId, $currency);
    }

    protected function stripeAmountToDecimal(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
