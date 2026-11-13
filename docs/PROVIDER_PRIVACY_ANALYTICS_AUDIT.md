# Provider, analytics and SSL checkpoint — 25 October 2026

Scope: existing production Cardora, no RunPod/model requests, no paid upgrades, no new customer verification or payment. Evidence directory: `D:/Cardora/RuntimeValidation/privacy-ssl-20261025`. This is a bounded technical audit, not legal certification.

## Later follow-up — 25 October, 15:30 Europe/Athens

The follow-up found and contained public access to legacy verification documents and an internal log path. All 66 document records and 122 associated files are now outside the web root; public URLs are blocked and downloads are admin-only. Sixteen isolated expiry/deletion checks pass. See [LEGACY_PRIVACY_REMEDIATION.md](LEGACY_PRIVACY_REMEDIATION.md) for verified facts, scope, evidence and recovery. Historical access assessment, backup expiry and the archive retention decision remain open, not declared resolved.

The agreed post-release checks are now scheduled as one-off thread automations for **7 November, 21 November and 19 December 2026 at 11:00 local time** (`cardora-2`, `cardora-4`, `cardora-8`). They are read-only and prohibit RunPod, inference, paid APIs, new subscriptions and production changes.

Hostinger still shows Installing and normal direct origin TLS still fails as expired. HTTP-01 exact-probe checks were repeated successfully on all four host/scheme combinations; the disposable probe was removed again. No CAA restrictions were returned by the public DNS query. Hostinger's current support guide allows up to two hours for Installing; changing apex/www DNS away from Pages would disrupt the frontend and was not done. Google Soft 404 validation likewise remains started, not completed.

## GA4: configured and receipt verified

Correct property: Cardora.gr, property 552519370, web stream 15858164845, measurement ID G-Q4WX9WDS2V. Existing GTM container GTM-K77KBTL8 retained.

- Disabled Enhanced Measurement browser-history page views and optional scroll, outbound-click, site-search, form, video and download events. Reopened the saved editor to verify. The fixed page-load option remains displayed; application code already sets `send_page_view: false` and emits controlled route events.
- Google Signals and user-provided data collection were already off. Changed ads personalization from 307 allowed regions to zero. Retention observed, not shortened: event data two months, user data fourteen months, reset on new activity enabled.
- Actual production browser: without consent there were no Google or Chatwoot script elements. Enabled analytics only, visited hub then cards category. GA4 Realtime received exactly two page views, one for each title, plus one session start. After visiting sign-in, withdrawing consent and returning to the hub, the total remained two. Google/Chatwoot scripts were again absent. This validates this sequence, not every possible route or tag configuration.
- Opening the support explanation alone also loaded neither provider. Did not open/send a Chatwoot conversation.
- Evidence: `ga4-enhanced-saved.png`, `ga4-two-pageviews.png`, `ga4-consent-withdrawal.txt`, `production-support-privacy.png`.

## Search Console: verified property, sitemap and baseline

Property: `sc-domain:cardora.gr`. Submitted `https://cardora.gr/sitemap.xml`; status Success, 738 discovered pages, submitted/read 25 October. Discovery does not mean every URL is indexed.

28-day report (26 September–23 October): 63 clicks, 339 impressions, 18.6% CTR, average position 46.5. Saved all 77 visible query rows to `gsc-queries-28d.json`, with full UI text in `gsc-28-day-baseline.txt`. The structured export was corrected from an initial overly broad table selection; the final file is parsed from the specific visible query table. Query totals can differ from aggregate totals.

Leading observed searches: `cardora` (7 clicks/39 impressions), `pokemon 30th`, `pokemon 30th anniversary`, `pokemon 30th anniversary greece` (each 2/7). This is real observed demand, not estimated keyword volume or evidence of broad category success.

Indexing report last updated 16 October: 504 indexed, 355 excluded: 257 redirects, 85 Google-selected alternative canonicals, ten crawled/currently not indexed, three Soft 404. These predate the release and are not all failures. The new `/el/syllektika` URL inspection already shows **indexed**, successful smartphone fetch on 25 October at 12:56:48 PM as displayed, indexing allowed, and matching declared/Google canonical. Its sitemap subsection still shows a temporary processing error, while the separate sitemap submission is Success.

The three Soft 404 examples were checked directly. Two www product URLs now return real 404. An obsolete product URL on `api.cardora.gr` still returned the old empty SPA, so a scoped edge redirect was added (see deployment runbook). It now returns 301 to the canonical site, whose removed product returns 404. Actual API JSON and the public hub remain 200. Google accepted **Validate fix**, status **Validation started, 25 October**; completion is pending Google. Evidence: `gsc-soft404-validation-started.png`, `api-product-redirect-results.json`.

Google reports no current Core Web Vitals field data. Structured reports each had two valid/zero invalid product snippets, merchant listings and breadcrumbs; these are Google's small reported sample, not an inventory-wide certification. Follow-up measurements remain 7 November, 21 November and 19 December, not automatically scheduled.

## Didit: actual provider settings verified

Authenticated organization VRealm, Cardora website, live My Application. The backend's configured workflow prefix matches the Compliance workflow, V4 live. Read-only inspection shows ID document/OCR, liveness, face match, device/IP analysis and automated final decision. No proof-of-address, bank/IBAN-document or AML step appears. Desktop access is disabled, consistent with mobile handoff. No session was created or decision overridden.

- Provider retention: **one year**. Biometric template: **Delete with session**. These were confirmed after asynchronous values loaded; initial placeholders were not used as evidence. Neither setting was changed.
- Organization model improvement was enabled. Opted out and revisited the settings: **OPTED OUT**, toggle off. This excludes historical and new organization verification data from future model-improvement work; it does not reverse earlier training or itself delete retained verification records. Evidence: `didit-privacy-optout-detail.png`.
- Live aggregate backend audit at 11:59 UTC: enabled/configured; notice version 2026-09-27.2; seven sessions; zero due-but-undeleted, zero overdue approved, zero approved missing expiry. Scheduler heartbeat was current. New integration table has reference/status/consent/timestamp/deletion fields, not image or biometric-payload columns.
- Existing bilingual notice describes the observed workflow, separate biometric consent, annual cycle and limited new-integration database storage. No unsupported promise of deleting all historic identity data was added.
- Provider publishes a DPA in Annex 2 of its [Business Terms](https://didit.me/terms/business/) and a [Verification Privacy Notice](https://didit.me/terms/verification-privacy-notice/). No contract was signed/accepted and no new provider terms were approved by this audit.

Limits remaining: no real end-to-end expiry/deletion exercise; legacy identity archives, logs and backups have not been certified erased; documented policy/legal review remains distinct from technical settings. No personal document or selfie was opened/downloaded.

## Legacy SSL: renewal pending provider completion

The visitor-facing Cloudflare certificate and the separate public-render origin certificate work. The old Hostinger origin certificate for cardora.gr/www was expired. Added a narrowly restricted public HTTP-01 validation route and confirmed exact probe retrieval over HTTP and HTTPS for both hostnames. Reinstalled the existing included Lifetime SSL in Hostinger; the last UI status is **Installing**, with provider guidance of up to an hour. Do not call this repaired until a normal direct TLS handshake validates the new certificate for both names.

No global SSL mode or certificate validation was weakened, no DNS was moved away from Pages, and no paid certificate was ordered. See `PUBLIC_RENDER_DEPLOYMENT.md` for worker routes, source, rollback and remaining verification.
