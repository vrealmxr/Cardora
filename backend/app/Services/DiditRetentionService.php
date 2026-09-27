<?php

namespace App\Services;

use App\Models\DiditSession;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiditRetentionService
{
    public function expireUser(int $userId): void
    {
        if (! IdentityVerification::where('user_id', $userId)->where('expires_at', '<=', now())
            ->where('status', '!=', 'Kyc Expired')->exists()) return;

        DB::transaction(function () use ($userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $verification = IdentityVerification::where('user_id', $userId)->first();
            if (! $verification?->expires_at || $verification->expires_at->gt(now())) return;
            $verification->update(['source' => 'didit', 'status' => 'Kyc Expired', 'verified_at' => null]);
            User::whereKey($userId)->update(['is_verified_seller' => false, 'trust_status' => 'basic']);
        }, 3);
    }

    public function deleteExpiredSession(DiditSession $session): bool
    {
        $session->refresh();
        if ($session->session_deleted_at || $session->retention_due_at->gt(now())) return true;
        $this->expireUser($session->user_id);
        $session->update([
            'deletion_requested_at' => $session->deletion_requested_at ?? now(),
            'deletion_attempted_at' => now(),
            'deletion_attempts' => $session->deletion_attempts + 1,
        ]);

        try {
            $response = Http::acceptJson()->asJson()->withHeaders(['x-api-key' => config('didit.api_key')])
                ->connectTimeout(5)->timeout(20)
                ->delete(config('didit.base_url').'/session/'.$session->session_id.'/delete/', [
                    // Do not retain a biometric template after this session is deleted.
                    // Operational retention, not an erasure of the user's NEW annual session.
                    'retain_face_embeddings' => false,
                    'instruction_id' => 'cardora-annual-'.$session->session_id,
                ]);
            $outcome = $response->json('face_retention_outcome');
            $confirmed = $response->status() === 204 || $response->status() === 404
                || ($response->status() === 200 && in_array($outcome, ['deleted', 'none', 'ineligible_no_vendor_user'], true));
            if (! $confirmed) {
                $session->update(['deletion_outcome' => 'unconfirmed_http_'.$response->status()]);
                Log::warning('Didit annual deletion unconfirmed', ['local_session_id' => $session->id, 'http_status' => $response->status()]);
                return false;
            }
            // Keep only minimal evidence/tombstones. Never claim a 404 proves global erasure.
            $session->update(['session_deleted_at' => now(), 'verification_url' => '',
                'deletion_outcome' => $response->status() === 404 ? 'session_already_absent' : ($outcome ?: 'session_deleted')]);
            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            $session->update(['deletion_outcome' => 'connection_failed']);
            Log::warning('Didit annual deletion connection failed', ['local_session_id' => $session->id]);
            return false;
        }
    }
}
