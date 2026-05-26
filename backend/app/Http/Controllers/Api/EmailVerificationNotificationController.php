<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('api.auth.email_already_verified'),
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => app()->getLocale() === 'en'
                    ? 'We could not send a verification email right now. Please try again in a few minutes.'
                    : 'Δεν ήταν δυνατή η αποστολή email επιβεβαίωσης αυτή τη στιγμή. Δοκίμασε ξανά σε λίγα λεπτά.',
            ], 503);
        }

        return response()->json([
            'message' => __('api.auth.verification_link_sent'),
        ]);
    }
}
