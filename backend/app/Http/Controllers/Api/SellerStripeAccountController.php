<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerPayoutAccount;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;

class SellerStripeAccountController extends Controller
{
    public function show(Request $request, StripeConnectService $connectService)
    {
        $seller = $request->user()->load('sellerPayoutAccount');
        $account = $seller->sellerPayoutAccount;

        if (! $account) {
            return response()->json([
                'data' => [
                    'id' => null,
                    'seller_id' => $seller->getKey(),
                    'stripe_account_id' => null,
                    'account_type' => 'express',
                    'onboarding_completed' => false,
                    'charges_enabled' => false,
                    'payouts_enabled' => false,
                    'details_submitted' => false,
                    'country' => null,
                    'default_currency' => null,
                    'requirements' => [],
                    'is_fully_onboarded' => false,
                    'updated_at' => null,
                ],
            ]);
        }

        $account = $connectService->syncLocalAccount($account);

        return response()->json([
            'data' => $this->serializeAccount($account),
        ]);
    }

    public function startOnboarding(Request $request, StripeConnectService $connectService)
    {
        $seller = $request->user()->load('sellerPayoutAccount');
        $account = $seller->sellerPayoutAccount ?: $connectService->createExpressAccount($seller);
        $account = $connectService->syncLocalAccount($account);
        $link = $connectService->createAccountLink($account->stripe_account_id, $account);

        return response()->json([
            'message' => 'Stripe onboarding link created.',
            'data' => [
                'account' => $this->serializeAccount($account),
                'onboarding_url' => $link['url'],
                'expires_at' => $link['expires_at'],
            ],
        ]);
    }

    public function dashboardLink(Request $request, StripeConnectService $connectService)
    {
        $seller = $request->user()->load('sellerPayoutAccount');
        $account = $seller->sellerPayoutAccount;

        if (! $account?->stripe_account_id) {
            abort(422, 'Stripe connected account not found for this seller.');
        }

        $account = $connectService->syncLocalAccount($account);

        return response()->json([
            'data' => [
                'url' => $connectService->createDashboardLoginLink($account->stripe_account_id),
                'account' => $this->serializeAccount($account),
            ],
        ]);
    }

    protected function serializeAccount(SellerPayoutAccount $account): array
    {
        return [
            'id' => $account->getKey(),
            'seller_id' => $account->seller_id,
            'stripe_account_id' => $account->stripe_account_id,
            'account_type' => $account->account_type,
            'onboarding_completed' => (bool) $account->onboarding_completed,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'details_submitted' => (bool) $account->details_submitted,
            'country' => $account->country,
            'default_currency' => $account->default_currency,
            'requirements' => $account->raw_requirements ?? [],
            'is_fully_onboarded' => $account->isFullyOnboarded(),
            'updated_at' => optional($account->updated_at)->toIso8601String(),
        ];
    }
}
