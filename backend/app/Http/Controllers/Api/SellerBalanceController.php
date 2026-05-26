<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\SellerLedgerService;
use App\Services\StripeConnectService;
use App\Services\StripeMarketplaceService;
use Illuminate\Http\Request;

class SellerBalanceController extends Controller
{
    public function summary(
        Request $request,
        SellerLedgerService $ledgerService,
        StripeConnectService $connectService,
        StripeMarketplaceService $marketplaceService
    ) {
        $seller = $request->user()->load('sellerPayoutAccount');
        $summary = $ledgerService->summaryForSeller($seller);
        $account = $seller->sellerPayoutAccount;

        $stripeAccountSummary = null;
        $connectedAccountBalance = null;

        if ($account?->stripe_account_id) {
            $account = $connectService->syncLocalAccount($account);
            $stripeAccountSummary = [
                'stripe_account_id' => $account->stripe_account_id,
                'onboarding_completed' => (bool) $account->onboarding_completed,
                'charges_enabled' => (bool) $account->charges_enabled,
                'payouts_enabled' => (bool) $account->payouts_enabled,
                'details_submitted' => (bool) $account->details_submitted,
                'country' => $account->country,
                'default_currency' => $account->default_currency,
            ];

            try {
                $connectedAccountBalance = $marketplaceService->retrieveBalanceForConnectedAccount($account->stripe_account_id);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'data' => array_merge($summary, [
                'stripe_account' => $stripeAccountSummary,
                'connected_account_balance' => $connectedAccountBalance,
            ]),
        ]);
    }

    public function payoutHistory(Request $request)
    {
        $payouts = Payout::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('processed_at')
            ->latest('created_at')
            ->get()
            ->map(fn (Payout $payout) => [
                'id' => $payout->getKey(),
                'amount' => (float) $payout->amount,
                'currency' => $payout->currency,
                'status' => $payout->status,
                'destination' => $payout->destination,
                'stripe_transfer_id' => $payout->stripe_transfer_id,
                'stripe_payout_id' => $payout->stripe_payout_id,
                'connected_account_id' => $payout->connected_account_id,
                'requested_at' => optional($payout->requested_at)->toIso8601String(),
                'processed_at' => optional($payout->processed_at)->toIso8601String(),
                'arrival_date' => optional($payout->arrival_date)->toIso8601String(),
                'metadata' => $payout->metadata ?? [],
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $payouts,
        ]);
    }
}
