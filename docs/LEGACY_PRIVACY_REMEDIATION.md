# Legacy verification privacy remediation — 25 October 2026

## Confirmed findings and containment

The read-only live inventory found **66 historical verification-document records** across identity (35), bank (17), and address (14), on the public disk. Anonymous HEAD probes to one real legacy document returned **200 / application/pdf** on both cardora.gr and api.cardora.gr. No body or document image was retrieved. This confirms anonymous availability of that sampled document; it does not establish who previously downloaded anything.

An independent HEAD probe to `/backend/storage/logs/laravel.log` returned **200 / text/plain**. The `.env` probe was **403**; it was not downloaded. Internal application files were consequently blocked as a separate boundary.

Containment completed at approximately **15:22 Europe/Athens** (13:22 UTC):

- All three legacy verification directories were moved from `backend/storage/app/public/uploads/verification-{identity,bank,address}` into `/home/u912666299/cardora-private/legacy-verification/uploads/`. This is outside the web root. All **122 files** (documents plus derived/other associated files, including directory protection files) passed SHA256 comparison. All 66 document records now use a private `legacy_verification` filesystem disk; no document was deleted. Directories are 0700, files 0600.
- Cloudflare Worker `cardora-legacy-verification-guard`, version **8b2de82d-bf80-4441-b89e-ad36bdde9c8a**, blocks the old verification storage paths and `/backend/*` on apex/www/API hosts, including cached objects at those paths. Config: `frontend/wrangler.legacy-verification.toml`; implementation: `frontend/src/server/legacyVerificationGuard.js`. No paid upgrade, credentials or RunPod call.
- Root `.htaccess` separately rejects the internal `backend` URL subtree. Normal PHP filesystem includes, `/api`, `/index.php`, `/storage` product images and admin routes remain separate. New root SHA256 **b6bba5e833cd7d62123a123b058100a189588c7d8f6b88a191ddfcdde5b0a6e6**. The source's direct HTTP test redirects to HTTPS; because the origin certificate is still expired, a normal direct TLS check remains blocked. Edge protection is independently verified.
- New download route `/admin/private-verification-document/{document}` uses the existing web session and requires `is_admin === true`. It resolves only private legacy records, checks the real path remains inside the private root, returns attachment/no-store/nosniff and a restrictive CSP. Model links now point to this route. SEO-editor access is explicitly excluded.

An initial per-directory `.htaccess` restriction did not eliminate the anonymous 200 responses, so it was not treated as successful containment. The directories were moved and edge protection was then independently verified.

## Verification

Evidence directory: `D:/Cardora/RuntimeValidation/privacy-ssl-20261025`.

- `legacy-private-verification.json`: 66 private records, 122 matching hashes, zero original public files; legacy URL HEAD returns 404 on apex/www/API; actual anonymous download returns 403. Direct controller tests with synthetic in-memory request identities: guest/member/SEO editor 403, admin 200 attachment/no-store with the preserved file. No real administrator download or document-body response was sent by the harness.
- `post-privacy-regression.json`: eight normal public/admin/API/sitemap/robots requests return expected 200; internal log URL returns 404. This is a bounded regression, not a full marketplace/payment test.
- `retention-isolated-results.txt`: 16 assertions passed using actual deployed Didit retention service and command, real migrations in **SQLite :memory:** and intercepted HTTP with stray requests forbidden. Covers provider failure, one-hour retry delay, badge expiry, explicit biometric deletion flag, acceptable/refused outcomes, 204 success, URL clearing, idempotence and preserving a newer annual approval. No production records, live provider sessions or shared cache were modified. Initial harness failures were missing PHPUnit assertions, reused fake response sequence and a stale fixture model; fixed in the harness, not by weakening production assertions.
- Live scheduler re-audit: seven real Didit sessions remain, zero due/undeleted, zero overdue approved, zero missing expiry; heartbeat remains current.
- `log-pattern-audit.json`: scanned current 2,518,070-byte Laravel log, 9,426 lines, returning counts only. No configured JSON indicators for document numbers, image/biometric payload, verification URLs or x-api-key headers matched. This narrow scan does **not** certify the log contains no personal data/secrets or that historical exposure was harmless.

## Backups, incident assessment and remaining decisions

Hostinger UI shows daily automated backups, latest file backup 24 October 17:42 as displayed. Listed file snapshots span 13 September–24 October. They were not downloaded, restored or deleted. These snapshots predate containment and may contain the original archive and log files. No permanent erasure or backup-expiry claim is made.

The accessible SSH logs include application/error logs but no identified HTTP access log for investigating historic downloads. A support request is prepared at `hostinger-support-draft.txt` for issuance investigation, preservation/access to relevant historical access logs and backup retention details. **Sending awaits explicit user authorization**. It requests no document contents, credentials, DNS changes, paid add-ons or deletion. Exposure assessment and any applicable legal response remain unresolved; absence of access evidence is not evidence of absence.

The private archive remains retained pending an explicit retention/deletion decision and appropriate review. Do not silently purge identity documents or historic backups. No new legal promise was published.

## Source, deployment and recovery

Private deployment stage `/home/u912666299/cardora-private/seo-legacy-privacy-20261025` contains the reviewed manifest, payload, pre-change code backup and restricted migration manifest. The latter includes paths/IDs and must **not** be copied into public source or logs. Source payload and installers are in `docs/deployment/legacy-private-payload`, `LegacyVerificationDocumentController.php`, `deploy-legacy-verification.php`, `migrate-legacy-verification.php`, and `restrict-internal-backend.php`. Installer checks hashes and PHP syntax before atomic replacements. Configuration and route caches were rebuilt and the route inspected before migration.

Keep edge and root protections when reverting unrelated application code. Never restore the original public storage layout as an ordinary rollback: that would reopen a verified exposure. If the download handler needs repair, keep the archive private and access denied while fixing it. Data is preserved, with a private recovery manifest and file hashes. Any change to private location/metadata requires preflight validation of every source/destination and unchanged hashes. Retain the private filesystem config until no record references it.

Do not blindly restore old whole-site backups: first reapply the privacy guard and move historical verification folders out of the public root before serving restored files. Existing old snapshots still need provider retention clarification.
