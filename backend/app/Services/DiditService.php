<?php

namespace App\Services;

use App\Models\DiditSession;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;

class DiditService
{
    public const STATUSES = ['Not Started', 'In Progress', 'Awaiting User', 'In Review', 'Approved', 'Declined', 'Resubmitted', 'Abandoned', 'Expired', 'Kyc Expired'];

    public function status(User $user): array
    {
        app(DiditRetentionService::class)->expireUser($user->id);
        $verification = IdentityVerification::where('user_id', $user->id)->first();
        if ($verification?->status === 'Kyc Expired' && $user->is_verified_seller) $user->refresh();
        return [
            'provider' => 'didit',
            'source' => $verification?->source ?? 'didit',
            'status' => $verification?->status ?? 'Not Started',
            'verified' => $verification?->status === 'Approved',
            'verified_at' => $verification?->verified_at?->toIso8601String(),
            'expires_at' => $verification?->expires_at?->toIso8601String(),
            'session_id' => $verification?->current_session_id,
            'available' => config('didit.enabled') && filled(config('didit.api_key')) && filled(config('didit.webhook_secret')),
            'notice_version' => config('didit.notice_version'),
        ];
    }

    public function createSession(User $user, string $locale): array
    {
        abort_unless(config('didit.enabled') && filled(config('didit.api_key')) && filled(config('didit.webhook_secret')), 503, 'Η επαλήθευση δεν είναι προσωρινά διαθέσιμη. / Verification is temporarily unavailable.');

        // Serialize attempts for the same user, including two browser tabs.
        return DB::transaction(function () use ($user, $locale) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            app(DiditRetentionService::class)->expireUser($user->id);
            $verification = IdentityVerification::firstOrCreate(['user_id' => $user->id]);
            abort_if($verification->status === 'Approved', 409, 'Ο λογαριασμός είναι ήδη επαληθευμένος. / Already verified.');
            $session = DiditSession::where('session_id', $verification->current_session_id)->first();
            abort_if($session?->status === 'In Review', 409, 'Η επαλήθευση εξετάζεται. / Verification is under review.');
            // Ask Didit to resume unfinished sessions so their callback is updated too.
            try {
                $response = $this->client()->post(config('didit.base_url').'/session/', [
                'workflow_id' => config('didit.workflow_id'),
                'vendor_data' => (string) $user->id,
                'callback' => rtrim(config('services.frontend.url', config('app.url')), '/').'/'.$locale.'/epalithefsi-apotelesma',
                // A QR handoff finishes on the phone, but only the initiating browser returns.
                'callback_method' => 'initiator',
                'language' => $locale,
                ]);
            } catch (ConnectionException $exception) {
                abort(503, 'Η υπηρεσία επαλήθευσης δεν είναι προσωρινά διαθέσιμη. / Verification is temporarily unavailable.');
            }
            // Never expose upstream bodies: they may contain personal data or tokens.
            abort_unless($response->successful(), 502, 'Η υπηρεσία επαλήθευσης δεν ανταποκρίθηκε. Δοκίμασε ξανά. / Please try verification again.');
            $data = $response->json();
            abort_unless(is_array($data) && Str::isUuid($data['session_id'] ?? '')
                && $this->safeVerificationUrl($data['url'] ?? '')
                && ($data['workflow_id'] ?? null) === config('didit.workflow_id')
                && (string) ($data['vendor_data'] ?? '') === (string) $user->id
                && in_array($data['status'] ?? '', self::STATUSES, true), 502, 'Invalid verification session.');
            $existing = DiditSession::where('session_id', $data['session_id'])->first();
            abort_if($existing && $existing->user_id !== $user->id, 502, 'Invalid verification session owner.');
            $session = DiditSession::firstOrCreate(['session_id' => $data['session_id']], [
                'user_id' => $user->id,
                'workflow_id' => config('didit.workflow_id'),
                'environment' => config('didit.environment'),
                'verification_url' => $data['url'],
                // Creation / browser callback cannot grant verification.
                'status' => 'Not Started',
                'notice_version' => config('didit.notice_version'),
                'consent_locale' => $locale,
                'consented_at' => now(),
                'retention_due_at' => now()->addYearNoOverflow(),
            ]);
            abort_if($session->retention_due_at->lte(now()) || $session->deletion_requested_at, 502, 'Provider returned an expired session.');
            $verification->update(['source' => 'didit', 'status' => $session->status, 'current_session_id' => $session->session_id,
                'verified_at' => null, 'expires_at' => $session->retention_due_at]);
            return ['session_id' => $session->session_id, 'url' => $session->verification_url];
        }, 3);
    }

    public function receive(array $event): string
    {
        abort_unless(in_array($event['webhook_type'] ?? '', ['status.updated', 'data.updated'], true), 422, 'Unsupported event.');
        abort_unless(Str::isUuid($event['event_id'] ?? '') && Str::isUuid($event['session_id'] ?? '')
            && is_int($event['created_at'] ?? null) && $event['created_at'] > 0
            && $event['created_at'] <= time() + 300
            && in_array($event['status'] ?? '', self::STATUSES, true), 422, 'Invalid event.');
        abort_unless(($event['environment'] ?? '') === config('didit.environment')
            && ($event['workflow_id'] ?? '') === config('didit.workflow_id')
            && ($event['session_kind'] ?? 'user') !== 'business', 422, 'Unexpected verification scope.');

        return DB::transaction(function () use ($event) {
            $session = DiditSession::where('session_id', $event['session_id'])->first();
            // Unknown sessions are never attached using vendor_data alone; retry a creation race.
            abort_unless($session, 503, 'Session not registered.');
            User::whereKey($session->user_id)->lockForUpdate()->firstOrFail();
            $session->refresh();
            abort_unless((string) ($event['vendor_data'] ?? '') === (string) $session->user_id
                && $session->environment === $event['environment'] && $session->workflow_id === $event['workflow_id'], 422, 'Session mismatch.');
            if (DB::table('didit_webhook_events')->where('event_id', $event['event_id'])->exists()) {
                return 'duplicate';
            }
            $outcome = 'applied';
            if ($session->retention_due_at->lte(now()) || $session->deletion_requested_at || $session->session_deleted_at) {
                app(DiditRetentionService::class)->expireUser($session->user_id);
                $outcome = 'expired';
            } elseif ($event['created_at'] < $session->provider_updated_at) {
                $outcome = 'stale';
            } elseif ($event['created_at'] === $session->provider_updated_at && $event['status'] !== $session->status) {
                // Equal timestamps cannot order different decisions. Reconcile through the API.
                $session->update(['last_reconciled_at' => null]);
                $outcome = 'reconcile';
            } else {
                $this->applyStatus($session, $event['status'], $event['created_at']);
            }
            DB::table('didit_webhook_events')->insert([
                'event_id' => $event['event_id'], 'session_id' => $session->session_id,
                'status' => $event['status'], 'outcome' => $outcome, 'created_at' => now(),
            ]);
            return $outcome;
        }, 3);
    }

    public function reconcile(User $user): void
    {
        app(DiditRetentionService::class)->expireUser($user->id);
        $verification = IdentityVerification::where('user_id', $user->id)->first();
        if (! $verification?->current_session_id || $verification->source !== 'didit') return;
        $session = DiditSession::where('session_id', $verification->current_session_id)->first();
        if (! $session || $session->retention_due_at->lte(now()) || $session->deletion_requested_at || $session->session_deleted_at || $session->last_reconciled_at?->gt(now()->subMinute())) return;
        $before = [$session->updated_at->toJSON(), $session->provider_updated_at, $session->status];
        $requestedAt = time();
        try {
            $response = $this->client()->get(config('didit.base_url').'/session/'.$session->session_id.'/decision/');
        } catch (ConnectionException $exception) {
            return;
        }
        if (! $response->successful()) return;
        $data = $response->json();
        if (($data['session_id'] ?? '') !== $session->session_id || ! in_array($data['status'] ?? '', self::STATUSES, true)) return;
        DB::transaction(function () use ($user, $session, $data, $before, $requestedAt) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session->refresh();
            // A webhook received during the request takes precedence.
            if ([$session->updated_at->toJSON(), $session->provider_updated_at, $session->status] !== $before) return;
            $this->applyStatus($session, $data['status'], max($session->provider_updated_at, $requestedAt));
            $session->update(['last_reconciled_at' => now()]);
        });
    }

    private function applyStatus(DiditSession $session, string $status, int $providerTime): void
    {
        // No late webhook or in-flight API response may renew an expired cycle.
        if ($session->retention_due_at->lte(now()) || $session->deletion_requested_at || $session->session_deleted_at) {
            app(DiditRetentionService::class)->expireUser($session->user_id);
            return;
        }
        $session->update(['status' => $status, 'provider_updated_at' => $providerTime]);
        $verification = IdentityVerification::where('user_id', $session->user_id)->first();
        if ($verification?->source !== 'didit' || $verification->current_session_id !== $session->session_id) return;
        $verification->update(['status' => $status, 'verified_at' => $status === 'Approved' ? ($verification->verified_at ?? now()) : null,
            'expires_at' => $session->retention_due_at]);
        User::whereKey($session->user_id)->update([
            'is_verified_seller' => $status === 'Approved',
            'trust_status' => $status === 'Approved' ? 'trusted' : (in_array($status, ['In Progress', 'In Review', 'Resubmitted', 'Awaiting User'], true) ? 'reviewing' : 'basic'),
        ]);
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->asJson()->withHeaders(['x-api-key' => config('didit.api_key')])->connectTimeout(5)->timeout(15);
    }

    private function safeVerificationUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'verify.didit.me'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port']);
    }
}
