<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\ApiResponse;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePlatformBalanceService
{
    protected ?StripeClient $client = null;

    public function syncHeldFundsReserve(): array
    {
        $minimumBalanceByCurrency = Order::query()
            ->selectRaw('currency, SUM(seller_amount) as held_amount')
            ->where('status', OrderStatus::PaidPendingRelease->value)
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(function ($row) {
                $currency = strtolower((string) $row->currency);
                $amount = max(0, (float) $row->held_amount);

                return [$currency => $this->toStripeAmount($amount)];
            })
            ->filter(fn (int $amount) => $amount > 0)
            ->all();

        $response = $this->stripe()->rawRequest(
            'post',
            '/v1/balance_settings',
            [
                'payments' => [
                    'payouts' => [
                        'minimum_balance_by_currency' => $minimumBalanceByCurrency,
                    ],
                ],
            ],
            [
                'stripe_version' => $this->balanceSettingsApiVersion(),
            ]
        );

        $decoded = $this->decodeResponse($response);

        Log::info('Stripe platform balance reserve synced.', [
            'minimum_balance_by_currency' => $minimumBalanceByCurrency,
            'response' => $decoded,
        ]);

        return $decoded;
    }

    public function getBalanceSettings(): array
    {
        $response = $this->stripe()->rawRequest(
            'get',
            '/v1/balance_settings',
            null,
            [
                'stripe_version' => $this->balanceSettingsApiVersion(),
            ]
        );

        return $this->decodeResponse($response);
    }

    protected function decodeResponse(ApiResponse $response): array
    {
        return (array) json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function balanceSettingsApiVersion(): string
    {
        return (string) config('services.stripe.balance_settings_api_version', '2025-09-30.clover');
    }

    protected function toStripeAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function stripe(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return $this->client = new StripeClient($secret);
    }
}
