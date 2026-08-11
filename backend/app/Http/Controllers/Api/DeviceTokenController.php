<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:512'],
            'platform' => ['nullable', 'string', 'in:ios'],
        ]);

        $user = $request->user();
        $platform = (string) ($validated['platform'] ?? 'ios');
        $token = trim((string) $validated['token']);

        UserDeviceToken::query()->updateOrCreate(
            [
                'platform' => $platform,
                'token' => $token,
            ],
            [
                'user_id' => $user->getKey(),
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['status' => 'ok']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:512'],
            'platform' => ['nullable', 'string', 'in:ios'],
        ]);

        $platform = (string) ($validated['platform'] ?? 'ios');
        $token = trim((string) $validated['token']);

        UserDeviceToken::query()
            ->where('user_id', $request->user()->getKey())
            ->where('platform', $platform)
            ->where('token', $token)
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
