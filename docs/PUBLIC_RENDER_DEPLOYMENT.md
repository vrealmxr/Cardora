# Public frontend rendering: deployment and rollback

**Latest order content release, 2026-10-29:** Pages `4df85563` (full ID in `D:/Cardora/RuntimeValidation/order-items-20261029/cloudflare-after.json`), renderer `492e05bd4b9fbd34`. Expandable order items with photos/details and participant-scoped sold-product context in messages are deployed. Actual live seller browser check passed without sending a message. See [ORDER_ITEMS_RELEASE_20261029.md](ORDER_ITEMS_RELEASE_20261029.md).

**Latest identity correction, 2026-10-29:** Pages `d80c4ccd-a87e-49d2-86e9-ba8f6f575015`, renderer `1f6dbc0fc823dbaa`. Order messaging now uses bootstrap `databaseId` instead of the display `CDR-…` identifier. Actual OrderCard click test and 12 live bootstrap/controller checks passed; production Greek/English served app verified. See the follow-up in [ORDER_MESSAGING_REPAIR_20261029.md](ORDER_MESSAGING_REPAIR_20261029.md).

**Latest release, 2026-10-29:** order participant messaging deployed as Pages `3765100e-dc15-4936-b716-05eb26168409`, renderer `9f75ef195febb7d1`. The order-card action now sends `order_id`; the scoped backend verifies actual buyer/seller and selects the order's listing. See [ORDER_MESSAGING_REPAIR_20261029.md](ORDER_MESSAGING_REPAIR_20261029.md). Greek/English served bundles verified; shipping artwork and existing branding preserved. Previous Pages `a3f3e29a-4fd6-4599-83e6-bf1271643b05` retained for rollback.

**Current release, 2026-10-29:** shipping partner logos deployed as Pages `a3f3e29a-4fd6-4599-83e6-bf1271643b05`, renderer `3e565ac871051013`. Shared `PartnerLogos.jsx` adds official BOX NOW/DHL SVG assets to the loader and footer, with Greek/English shipping labels and wrapping on small screens. Existing artwork/colors are retained. Preview `bf48658e` and production both verified in Greek/English, including actual served app and SVG artwork; desktop/mobile visual QA, build, scoped lint and all 6 origin checks passed. Evidence: `D:/Cardora/RuntimeValidation/shipping-logos-20261029`. Previous Pages `08aece6b-7bf5-4afb-92ee-27f6bdd91618` and renderer `a2c32bc2acf5fa90` retained for rollback. No backend source/configuration, RunPod or billing changes.

**Current release, 2026-10-28:** auction buyer access is deployed as Pages `08aece6b-7bf5-4afb-92ee-27f6bdd91618`, renderer `a2c32bc2acf5fa90`. See [AUCTION_BUYER_ACCESS_RELEASE.md](AUCTION_BUYER_ACCESS_RELEASE.md). Topology and existing proxy/bridge configuration remain unchanged; the IDs below are historical.

**Latest scoped release, 2026-10-28:** buying without seller verification is now deployed. Current Pages production is `462e3964-08bb-4b9c-b83f-0bb984663eaa`, renderer `84e2a67376177e4d`. Topology is unchanged. See [BUYER_ACCESS_RELEASE.md](BUYER_ACCESS_RELEASE.md) for the current source location, three-file backend scope, tests and rollback; deployment IDs below describe the earlier October 24 release.

Production verified 2026-10-24 (Europe/Athens). Source: `D:/CardoraGit/Cardora-scanner-live/frontend`. Evidence: `D:/Cardora/RuntimeValidation/seo-content-20261023`.

## Current topology

1. Cloudflare Pages `cardora-frontend`, production branch `main`, deployment `3e2d889d-0b01-4c86-8a05-a0e172944342`, serves assets/private CSR and routes anonymous public HTML requests through the thin Worker.
2. Both Pages preview and production call the Worker Custom Domain `public-render.cardora.gr`. This is **not** a Worker Route on `cardora.gr`. `wrangler.public-origin.toml` describes the dedicated `cardora-public-origin` Worker; workers.dev and preview URLs are disabled. It verifies the timestamp, payload digest, signed visitor partition and original visitor address. No cookie, Authorization or arbitrary upstream destination is accepted.
3. The bridge calls `https://public-origin.cardora.gr/index.php/api/public-html/<digest>?locale=el|en`, an independent Hostinger virtual host with an active included Lifetime SSL certificate. Cloudflare DNS: A `public-origin` -> `89.116.53.170`, DNS only. TLS verification remains enabled. The bridge constructs the two visitor headers only after verifying their HMAC.
4. The origin document root is `/home/u912666299/domains/cardora.gr/public_html/public-origin`. `index.php` and `.htaccess` come from `docs/deployment/public-origin-index.php` and `public-origin.htaccess`. Only signed GET requests to the public HTML endpoint enter Laravel. All general API, account, admin and upload paths are denied. ACME challenge files remain available for automatic certificate renewal. No secret lives in this web root.
5. Laravel retains its API middleware and existing signed 90/minute visitor partition. `PublicOriginRenderer` provides only anonymous public DTOs, fresh listing/profile visibility and current sitemap paths. It runs a bounded Node subprocess against the release-pinned bundle: `/home/u912666299/cardora-private/public-renderer/releases/f16d126301346499`. Node uses `--disable-wasm-trap-handler --max-old-space-size=128`, four process slots and an eight-second deadline. No persistent Node service, scheduler or model process is started.

The three existing signing-key copies remain private: local Codex private file, Laravel `storage/app/private/public-render-signing.key`, and Cloudflare secret bindings. Do not print their contents or commit them. No new account-management key was created.

## Why the routing is deliberate

The earlier full React Pages Worker exceeded the Workers Free CPU allowance. Separately, cross-zone requests collapsed onto a shared Worker IP and hit an upstream 30-request ceiling before PHP. Moving React to the existing host and preserving cryptographically verified visitor attribution resolved the reproduced failures without raising quotas or disabling WAF rules.

Do **not** change the bridge's origin back to `cardora.gr` plus `resolveOverride`. With the new production Pages Worker active, that path re-entered the frontend and returned a locale redirect for the API path. The isolated probe passed against the older frontend, so that probe alone was insufficient. A Pages `_routes.json` exclusion also failed to supply the required origin document. Neither workaround is in the final source. No Pages service binding is needed in the final topology.

The old main-domain Hostinger SSL still reports Failed; it was not altered. The new public-origin certificate was separately verified Active and by a normal TLS-validated HTTP request. This is not a certification of every existing backend TLS path.

## Deploy

- Build with `npm run build:pages`; this produces `dist`, `dist-origin`, and a manifest-bound renderer release. Run `npm run test:origin`, and the SEO/session/analytics checks when those components change.
- Upload the new renderer directory to the private releases path before publishing its Pages Worker. Verify each file hash and release manifest. Retain the previous renderer while any previous Pages deployment can still use it.
- Backend source lives in `D:/CardoraGit/Cardora/backend`, but most local legacy files are older than live. Never deploy the whole backend or overwrite `api.php`, `MarketplaceBootstrapService`, or access services. The scoped origin installer is staged at `/home/u912666299/cardora-private/seo-origin-20261024/deploy-public-origin.php`; its hash-guarded manifest covers the new service/controller and narrow signature/route additions.
- Update the bridge separately using its explicit Wrangler config; preserve the existing secret. Do not use the unrelated backend proxy configuration.
- Deploy Pages preview first, then `--branch main`. Immediately check actual `https://cardora.gr/el`, `/en/kartes`, and `/index.php/api/seo?locale=el`. Use an explicit `Cardora-Release-QA/1.0` User-Agent: default Python urllib was rejected with Cloudflare 1010 and caused an unnecessary rollback. Never disable browser integrity protection for the test.
- Run `scripts/audit-production.mjs <report.json>`. The bounded mixed audit accepts `CARDORA_PREVIEW_URL=https://cardora.gr`, `CARDORA_PUBLIC_FIXTURES=<evidence-dir>` and a unique report tag. It performs 78 public GETs, concurrency three with five-second pacing. This is functional regression evidence, not a capacity guarantee.

## Verified release evidence

- `production-verified-http-audit.json`: 31 requests, zero failed assertions; initial HTML, canonical/indexing rules, both languages, active/sold/unknown listings, private CSR, sitemap/robots, redirects, anonymous isolation and actual static assets.
- `preview-mixed-production-live.json`: 78/78 actual production requests, zero 429/5xx, one H1 and parseable structured data. This supersedes the passing isolated-probe results.
- `dedicated-origin-tests.log`: six transport/renderer tests passed, including bad signatures, forged visitor identity, stale timestamps, modified payloads and credential rejection.
- Browser: contact mailto decodes correctly through the existing Cloudflare email protection; category images load, original branding is preserved, no captured console errors. Screenshot: `production-category-verified.png`.
- `production-verified-state.json`: existing `cardora-backend-proxy` remains on version `dfff3b5d-94ff-46ee-b14b-3b95a6f7b245`, deployed September 15. Its code/routes, RunPod settings and inference were not touched. No paid plan or add-on was enabled.
- The temporary `cardora-public-release-check` Worker/route and old `cardora.gr/__cardora_public_html/*` route were removed. The temporary PHP entry trace was removed; root `index.php` SHA256 is restored to `107f14758690ceed59caec9ef16bb4605924d6d9dbc70b19ccbad382bff5e72f`. No tail is left running.

## Rollback

1. Restore the previous Pages production deployment `67858f4b-3f15-4f4f-b9d7-149ab20c4e18` first. Local `release-control.py --rollback` checks that the current production ID matches the recorded release before acting. Recheck HTTP and actual frontend behavior after propagation.
2. Keep the new origin/bridge available until no active deployment needs them. To retire them, remove the Custom Domain first (`configure-origin-domain.py --cleanup`, guarded by its saved ID), then remove only the dedicated `cardora-public-origin` script. The older `configure-origin-bridge.py --cleanup` expects a route which has now been removed: **do not use that cleanup mode**.
3. Remove the dedicated Hostinger subdomain/DNS only after confirming no references remain. Its directory is named above; retain source and release/backup files for recovery. Do not delete the parent public_html or Laravel tree.
4. If reverting Laravel additions, use the private origin installer's guarded `--rollback`, then rebuild route cache. Restore origin-stage changes **before** the older public-attribution/public-data rollbacks described in `SEO_CONTENT_GOAL.md`. Retain harmless new classes for in-flight requests as the installer does.

GA4 receipt/configuration, GSC property/sitemap/baseline and Didit workflow/retention/model-use settings were verified on 25 October; see [PROVIDER_PRIVACY_ANALYTICS_AUDIT.md](PROVIDER_PRIVACY_ANALYTICS_AUDIT.md). Later field Core Web Vitals, Google validation and the documented privacy limits remain separate follow-ups.

## Scoped edge repairs — 2026-10-25

**Later security containment:** see [LEGACY_PRIVACY_REMEDIATION.md](LEGACY_PRIVACY_REMEDIATION.md). A third Free Worker now blocks internal `/backend/*` and retired verification-document URLs on apex/www/API hosts. The 66 legacy document records refer to private storage outside the web root, with an admin-only download route. The root `.htaccess` hash below from the earlier canonical rollback is historical; current hash is `b6bba5e833cd7d62123a123b058100a189588c7d8f6b88a191ddfcdde5b0a6e6`. Do not undo these protections during an unrelated rollback or restore old verification files to public storage.

Evidence: `D:/Cardora/RuntimeValidation/privacy-ssl-20261025`. No change to the existing Pages deployment or backend-proxy code. Both new Workers use the existing Free account; workers.dev and preview URLs are disabled and there are no secret bindings.

### Main-origin certificate HTTP-01 validation

- Source `frontend/src/server/acmeValidation.js`, deploy config `frontend/wrangler.acme.toml`. Deployed version `162bb98b-8c72-4f23-831b-6c653f08c755` (same source originally deployed from the evidence directory).
- Two exact route patterns: `cardora.gr/.well-known/acme-challenge/*` and `www.cardora.gr/.well-known/acme-challenge/*`. Their IDs and pre-change route inventory are in `acme-route-state.json` and `worker-routes-before.json`.
- Accepts only GET/HEAD, whitelisted hosts and one alphanumeric/underscore/hyphen token segment. Fetches that public token over standard HTTP-01 to the existing origin via `origin-proxy.cardora.gr`; no user cookies, Authorization or query data forwarded. Upstream redirects/non-200 responses become 404. Does not proxy private or application routes and does not alter global SSL settings.
- Probe returned exact content in all four HTTP/HTTPS and apex/www combinations. Hostinger free **Reinstall** then showed **Installing**. This is a pending renewal, not evidence of a new certificate. Normal direct TLS verification for `cardora.gr` and `www.cardora.gr` must pass before closing it; separate `public-origin.cardora.gr` already validates to 21 January 2027.
- The disposable probe was removed at 12:16 UTC using `acme-route-check.py --remove-probe`; no CA challenge files were removed. Keep the ACME routes for renewal.
- Rollback: verify both recorded route IDs still have the exact recorded patterns and script `cardora-acme-validation`, remove only those two routes, then remove that script if unreferenced. Existing backend routes must remain untouched. This would restore the pre-repair validation path and may prevent renewal.

### Obsolete product links on API hostname

- Source `frontend/src/server/legacyProductRedirect.js`, config `frontend/wrangler.legacy-product.toml`, deployed version `c778092d-988e-4a33-b850-3d7f7cb92091`.
- Routes only `api.cardora.gr/el/proion/*` and `api.cardora.gr/en/proion/*`; IDs in `legacy-product-routes.json`. Valid GET/HEAD slugs return 301 to fixed `https://cardora.gr/<locale>/proion/<slug>` without query data. Invalid paths return 404; writes return 405. No upstream request is made.
- Actual legacy URL now redirects to its canonical removed-product 404. Both root/API-host SEO JSON endpoints remain 200 and hub HTML remains 200. Four scoped unit tests cover both workers, rejected paths/methods/hosts, stripped credentials/query data and rejected upstream redirects.
- An initial origin `.htaccess` attempt had no effect because the existing proxy rewrites the Host header. It was immediately hash-guarded rolled back. Root `.htaccess` is restored to SHA256 `047bda598b7cb695db55265235d0c4b0fcc0a001fcefa1012ad061722d040fde`. Failed attempt evidence/backup is retained privately; do not reapply that installer.
- Rollback: verify the two recorded route IDs still point to `cardora-legacy-product-redirect`, delete only those routes, then remove its script if unreferenced. The original `api.cardora.gr/*` route and backend-proxy version remain intact.
