<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationRedirectController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->redirectToFrontend('invalid');
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->redirectToFrontend('invalid');
        }

        if (! $request->hasValidSignature()) {
            return $this->redirectToFrontend('expired');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->redirectToFrontend('already-verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->redirectToFrontend('verified');
    }

    protected function redirectToFrontend(string $status): RedirectResponse
    {
        $baseUrl = rtrim((string) config('services.frontend.email_verification_url'), '/');

        return redirect()->away(sprintf('%s?status=%s', $baseUrl, urlencode($status)));
    }
}
