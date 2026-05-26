<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SellerPayoutAccount;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;

class StripeConnectRedirectController extends Controller
{
    public function refresh(Request $request, SellerPayoutAccount $account, StripeConnectService $connectService)
    {
        abort_unless($request->hasValidSignature(), 403);

        $account = $connectService->syncLocalAccount($account);
        $link = $connectService->createAccountLink($account->stripe_account_id, $account);

        return redirect()->away($link['url']);
    }

    public function success(Request $request, SellerPayoutAccount $account, StripeConnectService $connectService)
    {
        abort_unless($request->hasValidSignature(), 403);

        $account = $connectService->syncLocalAccount($account);
        $frontendUrl = rtrim((string) config('services.stripe.connect_return_frontend_url', config('app.frontend_url', 'http://localhost:5173/dashboard-politi')), '/');
        $separator = str_contains($frontendUrl, '?') ? '&' : '?';

        return redirect()->away(sprintf(
            '%s%sstripe_connect=success&account=%s&ready=%s',
            $frontendUrl,
            $separator,
            urlencode((string) $account->stripe_account_id),
            $account->isFullyOnboarded() ? '1' : '0',
        ));
    }
}
