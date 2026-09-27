<?php

namespace Tests\Feature;

use App\Models\DiditSession;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Models\SellerPayoutAccount;
use App\Services\DiditService;
use App\Services\DiditRetentionService;
use App\Services\MarketplaceAccessService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiditVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise the real verification migrations in isolated in-memory SQLite.
        // Unrelated legacy marketplace migrations contain MySQL-only FK changes.
        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_03_31_112439_add_admin_fields_to_users_table.php',
            '2026_03_31_220000_add_locale_to_users_table.php',
            '2026_03_31_230000_add_public_profile_fields_to_users_table.php',
            '2026_07_01_120100_add_shipping_origin_to_users_table.php',
            '2026_03_31_120000_create_verification_submissions_table.php',
            '2026_03_31_120001_create_verification_documents_table.php',
            '2026_04_03_190100_create_seller_payout_accounts_table.php',
            '2026_09_27_050000_create_didit_verification_tables.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        config(['didit.enabled' => true, 'didit.api_key' => 'test-api-key', 'didit.webhook_secret' => 'test-secret', 'didit.environment' => 'live']);
        Http::preventStrayRequests();
    }

    private function createDiditSession(User $user): DiditSession
    {
        $session = DiditSession::create([
            'user_id' => $user->id, 'session_id' => (string) Str::uuid(), 'workflow_id' => config('didit.workflow_id'),
            'environment' => 'live', 'status' => 'Not Started', 'verification_url' => 'https://verify.didit.me/session/private',
            'notice_version' => config('didit.notice_version'), 'consent_locale' => 'el', 'consented_at' => now(),
            'retention_due_at' => now()->addYearNoOverflow(),
        ]);
        IdentityVerification::updateOrCreate(['user_id' => $user->id], ['source' => 'didit', 'status' => 'Not Started', 'current_session_id' => $session->session_id]);
        return $session;
    }

    private function event(DiditSession $session, string $status = 'Approved', int $age = 0): array
    {
        return ['event_id' => (string) Str::uuid(), 'session_id' => $session->session_id, 'vendor_data' => (string) $session->user_id,
            'workflow_id' => config('didit.workflow_id'), 'environment' => 'live', 'status' => $status,
            'webhook_type' => 'status.updated', 'timestamp' => time(), 'created_at' => time() - $age];
    }

    private function sendEvent(array $event, bool $valid = true)
    {
        ksort($event, SORT_STRING);
        $raw = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->call('POST', '/api/webhooks/didit', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => (string) $event['timestamp'],
            'HTTP_X_SIGNATURE_V2' => $valid ? hash_hmac('sha256', $raw, 'test-secret') : str_repeat('0', 64),
        ], $raw);
    }

    public function test_consent_authentication_and_server_owned_identity(): void
    {
        $this->postJson('/api/profile/verification/didit')->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/profile/verification/didit')->assertUnprocessable();
        $id = (string) Str::uuid();
        Http::fake(['*/session/' => Http::response(['session_id' => $id, 'url' => 'https://verify.didit.me/session/secret', 'workflow_id' => config('didit.workflow_id'), 'vendor_data' => (string) $user->id, 'status' => 'Approved'], 201)]);
        $this->postJson('/api/profile/verification/didit', ['consent' => true, 'notice_version' => config('didit.notice_version'), 'vendor_data' => 'another-user'])->assertCreated()->assertJsonPath('data.session_id', $id);
        Http::assertSent(fn ($request) => $request['vendor_data'] === (string) $user->id && $request['workflow_id'] === config('didit.workflow_id'));
        Http::assertSent(fn ($request) => $request['callback_method'] === 'initiator'
            && str_ends_with($request['callback'], '/el/epalithefsi-apotelesma'));
        $this->assertFalse($user->fresh()->is_verified_seller);
        $this->assertDatabaseHas('didit_sessions', ['session_id' => $id, 'notice_version' => config('didit.notice_version')]);
        $this->assertNotSame('https://verify.didit.me/session/secret', DiditSession::first()->getRawOriginal('verification_url'));
    }

    public function test_resuming_updates_provider_callback_without_extending_retention(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = $this->createDiditSession($user);
        $deadline = $session->retention_due_at->toIso8601String();
        Http::fake(['*/session/' => Http::response([
            'session_id' => $session->session_id, 'url' => $session->verification_url,
            'workflow_id' => config('didit.workflow_id'), 'vendor_data' => (string) $user->id,
            'status' => 'In Progress',
        ], 201)]);
        $this->postJson('/api/profile/verification/didit', ['consent' => true, 'notice_version' => config('didit.notice_version')])
            ->assertCreated()->assertJsonPath('data.session_id', $session->session_id);
        Http::assertSent(fn ($request) => $request['callback_method'] === 'initiator'
            && str_ends_with($request['callback'], '/el/epalithefsi-apotelesma'));
        $this->assertSame($deadline, $session->fresh()->retention_due_at->toIso8601String());
        $this->assertDatabaseCount('didit_sessions', 1);
        $this->assertFalse($user->fresh()->is_verified_seller);
    }

    public function test_review_session_does_not_create_another_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = $this->createDiditSession($user);
        $session->update(['status' => 'In Review']);
        $this->postJson('/api/profile/verification/didit', ['consent' => true, 'notice_version' => config('didit.notice_version')])->assertConflict();
        Http::assertNothingSent();
    }

    public function test_signed_decisions_are_idempotent_and_stale_events_do_not_revoke(): void
    {
        $user = User::factory()->create();
        $session = $this->createDiditSession($user);
        $event = $this->event($session);
        $this->sendEvent($event, false)->assertUnauthorized();
        $this->assertFalse($user->fresh()->is_verified_seller);
        $this->sendEvent($event)->assertOk();
        $this->sendEvent($event)->assertOk()->assertJsonPath('outcome', 'duplicate');
        $this->sendEvent($this->event($session, 'Declined', 100))->assertOk()->assertJsonPath('outcome', 'stale');
        $this->assertTrue($user->fresh()->is_verified_seller);
        $this->assertDatabaseCount('didit_webhook_events', 2);
    }

    public function test_wrong_environment_owner_workflow_and_unknown_session_cannot_verify(): void
    {
        $user = User::factory()->create();
        $session = $this->createDiditSession($user);
        foreach (['environment' => 'sandbox', 'vendor_data' => '999999', 'workflow_id' => (string) Str::uuid()] as $key => $value) {
            $this->sendEvent([...$this->event($session), $key => $value])->assertUnprocessable();
        }
        $this->sendEvent([...$this->event($session), 'session_id' => (string) Str::uuid()])->assertStatus(503);
        $this->sendEvent([...$this->event($session), 'timestamp' => time() - 301])->assertUnauthorized();
        $this->assertFalse($user->fresh()->is_verified_seller);
    }

    public function test_historical_sessions_cannot_override_current_session(): void
    {
        $user = User::factory()->create();
        $old = $this->createDiditSession($user);
        $current = $this->createDiditSession($user);
        $this->sendEvent($this->event($old))->assertOk();
        $this->assertFalse($user->fresh()->is_verified_seller);
        $this->sendEvent($this->event($current))->assertOk();
        $this->assertTrue($user->fresh()->is_verified_seller);
    }

    public function test_didit_approval_still_requires_stripe_but_no_address_or_bank_upload(): void
    {
        $user = User::factory()->create();
        $session = $this->createDiditSession($user);
        $this->sendEvent($this->event($session))->assertOk();
        $access = app(MarketplaceAccessService::class)->summaryForUser($user);
        $this->assertFalse($access['can_buy']);
        $this->assertNotContains('bank', $access['missing_keys']);
        $this->assertNotContains('address', $access['missing_keys']);
        $this->assertContains('stripe_connect', $access['missing_keys']);
    }

    public function test_transition_preserves_verified_users_except_two_exact_accounts_and_is_repeatable(): void
    {
        $kl = User::factory()->create(['handle' => 'KLCARDS', 'is_verified_seller' => true]);
        $mica = User::factory()->create(['handle' => 'micadopanther', 'is_verified_seller' => true]);
        $other = User::factory()->create(['handle' => 'other', 'is_verified_seller' => true]);
        $this->artisan('didit:prepare')->assertSuccessful();
        $this->assertDatabaseCount('identity_verifications', 0);
        $this->artisan('didit:prepare --apply')->assertSuccessful();
        $this->assertTrue($other->fresh()->is_verified_seller);
        $this->assertFalse($kl->fresh()->is_verified_seller);
        $this->assertFalse($mica->fresh()->is_verified_seller);
        $session = $this->createDiditSession($kl);
        $this->sendEvent($this->event($session))->assertOk();
        $this->artisan('didit:prepare --apply')->assertSuccessful();
        $this->assertTrue($kl->fresh()->is_verified_seller);
    }

    public function test_legacy_submission_and_upload_are_disabled(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/profile/verification', ['verification_type' => 'identity', 'payload' => []])->assertStatus(410);
    }

    public function test_api_reconciliation_recovers_missed_decision(): void
    {
        $user = User::factory()->create();
        $session = $this->createDiditSession($user);
        Http::fake(['*/decision/' => Http::response(['session_id' => $session->session_id, 'status' => 'Approved'])]);
        Sanctum::actingAs($user);
        $this->postJson('/api/profile/verification/didit/refresh')->assertOk()->assertJsonPath('data.verified', true);
        $this->assertTrue($user->fresh()->is_verified_seller);
    }

    public function test_annual_expiry_blocks_access_even_if_scheduler_did_not_run(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $session = $this->createDiditSession($user);
        $this->sendEvent($this->event($session))->assertOk();
        $this->travelTo($session->retention_due_at->copy()->subSecond());
        $this->assertTrue(app(DiditService::class)->status($user)['verified']);
        $this->travelTo($session->retention_due_at);
        $state = app(DiditService::class)->status($user);
        $this->assertFalse($state['verified']);
        $this->assertSame('Kyc Expired', $state['status']);
        $this->assertFalse($user->fresh()->is_verified_seller);
        $this->assertFalse(app(MarketplaceAccessService::class)->summaryForUser($user)['can_buy']);
        // A perfectly signed but late Approved event cannot start another year.
        $this->sendEvent($this->event($session))->assertOk()->assertJsonPath('outcome', 'expired');
        $this->assertFalse($user->fresh()->is_verified_seller);
        Http::assertNothingSent();
    }

    public function test_annual_deletion_is_confirmed_and_retries_failure_without_restoring_access(): void
    {
        $user = User::factory()->create();
        $session = $this->createDiditSession($user);
        $this->sendEvent($this->event($session))->assertOk();
        $this->travelTo($session->retention_due_at->copy()->addSecond());
        Http::fake(['*/delete/' => Http::sequence()->push([], 503)->push(['face_retention_outcome' => 'deleted'], 200)]);
        $this->artisan('didit:expire --delete')->assertFailed();
        $this->assertFalse($user->fresh()->is_verified_seller);
        $this->assertNull($session->fresh()->session_deleted_at);
        $this->assertSame('unconfirmed_http_503', $session->fresh()->deletion_outcome);
        $this->travel(61)->minutes();
        $this->artisan('didit:expire --delete')->assertSuccessful();
        $this->assertNotNull($session->fresh()->session_deleted_at);
        $this->assertSame('', $session->fresh()->verification_url);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request['retain_face_embeddings'] === false);
        $this->artisan('didit:expire --delete')->assertSuccessful();
        Http::assertSentCount(2);
    }

    public function test_retained_biometric_response_is_not_marked_as_deleted(): void
    {
        $session = $this->createDiditSession(User::factory()->create());
        $this->travelTo($session->retention_due_at->copy()->addSecond());
        Http::fake(['*/delete/' => Http::response(['face_retention_outcome' => 'retained_with_user'], 200)]);
        $this->assertFalse(app(DiditRetentionService::class)->deleteExpiredSession($session));
        $this->assertNull($session->fresh()->session_deleted_at);
    }

    public function test_renewal_requires_new_session_and_old_deletion_cannot_expire_new_approval(): void
    {
        $user = User::factory()->create();
        $old = $this->createDiditSession($user);
        $this->sendEvent($this->event($old))->assertOk();
        $this->travelTo($old->retention_due_at->copy()->addSecond());
        $newId = (string) Str::uuid();
        Http::fake([
            '*/session/' => Http::response(['session_id' => $newId, 'url' => 'https://verify.didit.me/session/new',
                'workflow_id' => config('didit.workflow_id'), 'vendor_data' => (string) $user->id, 'status' => 'Not Started'], 201),
            '*/delete/' => Http::response([], 204),
        ]);
        Sanctum::actingAs($user);
        $this->postJson('/api/profile/verification/didit', ['consent' => true, 'notice_version' => config('didit.notice_version')])
            ->assertCreated()->assertJsonPath('data.session_id', $newId);
        $new = DiditSession::where('session_id', $newId)->firstOrFail();
        $this->sendEvent($this->event($new))->assertOk();
        $this->assertTrue($user->fresh()->is_verified_seller);
        $this->assertTrue(app(DiditRetentionService::class)->deleteExpiredSession($old));
        $this->assertTrue($user->fresh()->is_verified_seller);
        $this->assertNull($new->fresh()->session_deleted_at);
        $this->assertTrue(IdentityVerification::where('user_id', $user->id)->first()->expires_at->equalTo($new->retention_due_at));
    }

    public function test_legacy_preservation_also_expires_after_one_year_with_confirmed_ids(): void
    {
        $kl = User::factory()->create(['handle' => 'klcards', 'is_verified_seller' => true]);
        $mica = User::factory()->create(['handle' => 'michaelnightbunny', 'is_verified_seller' => true]);
        $other = User::factory()->create(['is_verified_seller' => true]);
        $this->artisan('didit:prepare', ['--apply' => true, '--exception-user' => [$kl->id, $mica->id]])->assertSuccessful();
        $state = app(DiditService::class)->status($other);
        $this->assertTrue($state['verified']);
        $this->assertNotNull($state['expires_at']);
        $this->travelTo(\Illuminate\Support\Carbon::parse($state['expires_at']));
        $this->artisan('didit:expire')->assertSuccessful();
        $this->assertFalse($other->fresh()->is_verified_seller);
        $this->assertSame('Kyc Expired', app(DiditService::class)->status($other)['status']);
    }
}
