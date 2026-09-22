<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScannerUsage;
use Illuminate\Http\Request;

class ScannerUsageController extends Controller
{
    private const FREE_MONTHLY_LIMIT = 5;

    /**
     * Remaining AI Scanner uses this month. The scanner's actual
     * recognition/search logic lives elsewhere — this only meters usage of
     * the existing endpoint.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->isProActive()) {
            return response()->json(['data' => ['unlimited' => true, 'used' => null, 'remaining' => null, 'limit' => null]]);
        }

        $used = $this->usedThisMonth($user->getKey());

        return response()->json([
            'data' => [
                'unlimited' => false,
                'used' => $used,
                'remaining' => max(0, self::FREE_MONTHLY_LIMIT - $used),
                'limit' => self::FREE_MONTHLY_LIMIT,
            ],
        ]);
    }

    /**
     * Record one scan. Rejects with 402 once a FREE user has hit the
     * monthly cap so the frontend can show an upgrade prompt before running
     * (or after running out of) its preview search.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isProActive()) {
            $used = $this->usedThisMonth($user->getKey());

            if ($used >= self::FREE_MONTHLY_LIMIT) {
                return response()->json([
                    'message' => sprintf(
                        'Free accounts get %d AI Scanner uses per month. Upgrade to Cardora PRO for unlimited scans.',
                        self::FREE_MONTHLY_LIMIT
                    ),
                    'upgrade_required' => true,
                    'limit' => self::FREE_MONTHLY_LIMIT,
                ], 402);
            }
        }

        ScannerUsage::create(['user_id' => $user->getKey(), 'created_at' => now()]);

        $used = $this->usedThisMonth($user->getKey());

        return response()->json([
            'data' => [
                'unlimited' => $user->isProActive(),
                'used' => $user->isProActive() ? null : $used,
                'remaining' => $user->isProActive() ? null : max(0, self::FREE_MONTHLY_LIMIT - $used),
                'limit' => $user->isProActive() ? null : self::FREE_MONTHLY_LIMIT,
            ],
        ], 201);
    }

    private function usedThisMonth(int $userId): int
    {
        return ScannerUsage::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
