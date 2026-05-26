<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountEmailRequest;
use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\JsonResponse;
use Throwable;

class ProfileAccountController extends Controller
{
    public function updateEmail(UpdateAccountEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $email = (string) ($validated['email'] ?? '');

        if (strcasecmp($email, (string) $user->email) === 0) {
            $error = $this->message(
                'Use a different email address from your current one.',
                'Χρησιμοποίησε διαφορετικό email από το τωρινό σου.'
            );

            return response()->json([
                'message' => $error,
                'errors' => [
                    'email' => [$error],
                ],
            ], 422);
        }

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();

        $verificationEmailSent = true;

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
            $verificationEmailSent = false;
        }

        return response()->json([
            'message' => $this->message(
                'Your email was updated. Please verify your new email address.',
                'Το email σου ενημερώθηκε. Επιβεβαίωσε τη νέα διεύθυνση email.'
            ),
            'verification_email_sent' => $verificationEmailSent,
            'data' => new UserProfileResource(
                $user->fresh()->loadMissing('sellerPayoutAccount')
            ),
        ]);
    }

    public function updatePassword(UpdateAccountPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $currentTokenId = $user->currentAccessToken()?->getKey();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        if ($currentTokenId) {
            $user->tokens()->whereKeyNot($currentTokenId)->delete();
        }

        return response()->json([
            'message' => $this->message(
                'Your password was updated. Other active sessions were signed out.',
                'Ο κωδικός ενημερώθηκε. Οι άλλες ενεργές συνδέσεις αποσυνδέθηκαν.'
            ),
        ]);
    }

    protected function message(string $english, string $greek): string
    {
        return app()->getLocale() === 'en'
            ? $english
            : $greek;
    }
}

