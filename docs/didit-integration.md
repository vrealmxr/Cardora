# Didit rollout and annual lifecycle

Status: deployed and enabled in production on 2026-09-27. Automatic hosting scheduler execution was confirmed through advancing `didit:last_expiry_check` cache heartbeats at 02:19:02 and 02:20:02 UTC, independently of a prior manual scheduler test at 02:16:31. On the same date, after explicit user confirmation, the Didit application below was saved with `1 year` retention and `Delete with session` for biometric templates; both persisted after reload. A consented real camera/document verification remains to be completed by a test participant.

Didit console scope: VRealm, My Application / Live, application `af6e0c45-ca9d-47e9-8712-9f3ee1416de6`, organization `4fc34dcb-e795-42d5-93d4-abccb2efb9a7`. Model-training preferences were not changed. No DPA was signed or accepted by this implementation.

## Approved scope

- Replace Cardora's manual identity/address/IBAN submissions with the configured Didit workflow.
- Do not change Stripe or Stripe Connect requirements, payout flows, or shipping addresses.
- Workflow: `d057f8df-22ac-4a93-a9fc-aaccfe331f2c`.
- Webhook: `https://api.cardora.gr/index.php/api/webhooks/didit`.
- Preserve existing approvals except the explicitly confirmed users 9 (klcards) and 10 (michaelnightbunny / MikadoPanther).
- One calendar year per cycle, measured from session creation, using `addYearNoOverflow()` (February 29 maps to February 28 the next year). Existing preserved approvals receive one year from migration, not a fabricated historical verification date.
- Terms/privacy/consent notice version: `2026-09-27.2`. Archive that notice at deployment; bump its version when material disclosures change.

## Enforcement

### Hosted mobile-only redirect (2026-09-27 follow-up)

The current frontend uses a full-page redirect to the server-issued `https://verify.didit.me/…` URL, after the existing notice and explicit consent. The embedded SDK and Cardora-generated QR have been removed. Didit handles the QR/device handoff. A read-only check of published workflow v4 (`e862c342-7bde-4373-864a-b0dc3d2540ad`) confirmed `is_desktop_allowed=false`; no verification checks or workflow settings were changed by this follow-up.

Session creation sends `callback_method=initiator` and callback `https://cardora.gr/{locale}/epalithefsi-apotelesma`. After a desktop-to-phone QR handoff only the original device returns to Cardora; the phone stays on Didit's completion screen. If verification starts on a phone, that phone is the initiator and returns to Cardora. Unfinished sessions are resumed through the create API so Didit updates their callback, without extending their original Cardora retention deadline. In-review sessions cannot start another attempt. Old Cardora mobile QR links remain supported as a redirect to their existing Didit URL.

The return page ignores the untrusted callback `status`, compares the returned session identifier to authenticated backend state, refreshes decisions server-side and displays success, failure, pending review or further-action messages. It removes callback query parameters from browser history. A signed-out visitor is told to return to the original device, without a login prompt. Existing Cardora authentication behavior is unchanged. The exact Didit-hosted mobile completion copy and a full real cross-device verification still require a consenting test participant; no custom Didit-hosted text was configured.

Follow-up validation: 16 backend tests / 97 assertions, two Node redirect/result tests, scoped eslint and production build passed. Local browser QA checked pending, success, failure, mismatched sessions and ignoring a forged URL status. Existing dependency audit warnings were not addressed with a broad dependency upgrade in this scoped change.

`identity_verifications.expires_at` controls marketplace eligibility. Expiry is checked on access, session creation, reconciliation, and by `didit:expire` every minute. At the deadline the account becomes `Kyc Expired`, its badge is revoked, and a fresh session is required. Stripe state is never changed. Old webhook deliveries and API reconciliation cannot extend the deadline or approve an expired cycle.

Each session has an immutable `retention_due_at`. `didit:expire --delete` runs hourly, targeting only due sessions recorded by Cardora. It sends `DELETE /v3/session/{session_id}/delete/` with `retain_face_embeddings: false`. Failures are retried no more often than hourly, logged without provider payloads, displayed in the admin retention list, and produce a failed command exit. A 200 response must have an accepted non-retention outcome; 204 is supported for older deployments. A 404 is recorded as `session_already_absent`, not as proof of global privacy erasure.

After confirmed session deletion the encrypted session URL is cleared. Minimal session/consent/result/deletion records remain, including identifiers needed to reject queued old webhooks. Document images, selfie videos, biometrics and decision PDFs are not copied to Cardora. This minimal audit record is not an identity-document archive. Its retention and erasure must be covered by the controller's documented policy; it is not automatically deleted at the session-media deadline.

This is operational session deletion, not a user-wide privacy-erasure instruction: deleting an old cycle must not purge a new cycle. Data-subject erasure, parent Didit User records, independently stored blocklist entries, backups and legacy manually uploaded documents need separate handling. Do not present session deletion as erasure from every system.

## Activation checklist

1. Confirm Didit application retention = 1 year and biometric template policy = delete with session. Do not apply app-wide changes to an unrelated application. Model-training preferences are independent of retention and require a separate decision.
2. Document the purpose/necessity of one-year retention, applicable lawful bases, the DPA, processor/subprocessor and transfer safeguards, biometric consent/alternative-review handling, DPIA assessment, access controls, and rights-request procedures. This implementation is not a GDPR certification.
3. Back up exact affected production files and original verification flags. Preserve unrelated changes. Store backend secrets only in the ignored/server `.env`, never the frontend or deploy logs.
4. Deploy backend with `DIDIT_ENABLED=false`, then run only the Didit migration. Check PHP 8.1 syntax, route registration, webhook signature rejection and database schema before activation.
5. Dry run `php artisan didit:prepare --exception-user=9 --exception-user=10`. Inspect exact handles and changes; then use the same command with `--apply`. Rerunning does not overwrite existing migration records.
6. Configure the hosting scheduler to run `php artisan schedule:run` every minute with the production PHP binary. Verify actual execution, not merely `schedule:list`. This host's SSH environment currently has no `crontab` command; verify the hPanel scheduler. Monitor failures and the admin overdue-deletion filter.
7. Build/deploy frontend, enable Didit and rebuild config cache. Verify the public webhook, authenticated status, preserved accounts, exactly two immediate reverifications, fresh-user consent, embedded desktop/mobile flow, QR continuation and independent Stripe gates.
8. Use a consented test participant to complete a real verification. Do not simulate a production approval or submit another person's documents.

## Tests

Run `cd backend && php vendor/bin/phpunit --filter=Didit` and the frontend scoped eslint/build checks. Tests use isolated SQLite with the relevant real migrations because unrelated legacy migrations contain MySQL-only foreign-key alterations. HTTP is mocked and stray calls are prohibited. Annual tests use a frozen clock, including the exact deadline, old events, renewal, deletion failures/retries, retained-template responses and grandfathered approvals.

Local verification on 2026-09-27: 14 tests / 88 assertions passed, with one pre-existing PHP 8.5 database-constant deprecation. Scoped frontend lint and production build passed. Browser QA used a loopback-only mock API, not production credentials: expired state, consent gating, embedded SDK iframe, local QR rendering, API-driven approval and iframe removal, and mobile layout at 390px were checked. Mobile URL fragments were removed from history and there was no horizontal overflow. A real camera/ID verification remains a launch test, not something simulated by these checks.

## Production rollout record — 2026-09-27

- Backend deployed to the existing Hostinger Laravel application with PHP 8.1; only the Didit migration was applied. Configuration, routes and views were cached. No unrelated deployment script or schema migration was run.
- Original affected backend files, environment and account verification flags were backed up outside the webroot at `/home/u912666299/cardora-didit-backup-20260927.f7Lamo`, with private permissions for secrets and account snapshots. This path must not be served publicly.
- Frontend deployed to Cloudflare Pages project `cardora-frontend` (production domain `cardora.gr`); initial deployment `7d31040f`. The previous production deployment was `b906d74a-0d03-4513-814d-f1e0dac701d2`.
- Applied `didit:prepare --exception-user=9 --exception-user=10 --apply`: 19 existing users mapped, nine existing approvals preserved, and exactly IDs 9 and 10 lost their previous verified badges. Stripe-related user fields were hashed before and after the transition and were unchanged. New annual expiry dates were assigned to preserved approvals.
- Enabled `DIDIT_ENABLED=true` only after the scheduler and migration checks. Confirmed both exception users return an available Didit flow with `Not Started` and no approval.
- Production HTTP checks passed: homepage and mobile continuation route 200; unauthenticated verification status 401; unsigned webhook POST 401; public bootstrap 200 with Didit content. The served homepage matched the built frontend artifact.
- Hostinger Custom cron, every minute (all five timing fields `*`): `/opt/alt/php81/usr/bin/php /home/u912666299/domains/cardora.gr/public_html/backend/artisan schedule:run >> /home/u912666299/domains/cardora.gr/public_html/backend/storage/logs/scheduler.log 2>&1`. The heartbeat provides execution evidence even while the feature is disabled; an empty redirected log alone does not establish that cron failed.
- No real biometric session was created for testing, and no historical identity documents were deleted by this rollout. Legacy-document retention, the DPA and the governance items above remain separate responsibilities; deployment is not a GDPR certification.

## Rollback

Do not simply disable the feature after accepting Didit approvals: legacy checks do not represent new decisions, and a disabled scheduler would stop annual deletion. Take a maintenance window, preserve the new audit/session tables and due deletion queue, and reconcile account flags against the predeployment backup and subsequent valid decisions. Never automatically restore the two exception users' former approval. Never delete the new tables as a routine rollback once real sessions exist.

## Official references

- https://docs.didit.me/sessions-api/delete-session
- https://docs.didit.me/console/data-retention
- https://docs.didit.me/integration/webhooks
- https://didit.me/terms/verification-privacy-notice/

No production data was deleted during local automated tests.
