<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CardoraProSubscriptionService;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'plan' => $user->isProActive() ? 'pro' : 'free',
                'status' => $user->pro_status,
                'currentPeriodEnd' => $user->pro_current_period_end,
                'cancelAtPeriodEnd' => (bool) $user->pro_cancel_at_period_end,
                'trialAvailable' => $user->pro_trial_used_at === null,
                'featuredCreditAvailable' => (bool) $user->pro_featured_credit_available,
            ],
        ]);
    }

    public function checkout(Request $request, CardoraProSubscriptionService $subscriptions)
    {
        if ($request->user()->isProActive()) {
            return response()->json([
                'message' => 'You already have an active Cardora PRO subscription.',
            ], 422);
        }

        try {
            $checkoutUrl = $subscriptions->startCheckout($request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json(['data' => ['checkout_url' => $checkoutUrl]]);
    }

    public function cancel(Request $request, CardoraProSubscriptionService $subscriptions)
    {
        try {
            $subscriptions->cancelAtPeriodEnd($request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => ['cancelAtPeriodEnd' => true]]);
    }

    public function resume(Request $request, CardoraProSubscriptionService $subscriptions)
    {
        try {
            $subscriptions->resume($request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => ['cancelAtPeriodEnd' => false]]);
    }
}
