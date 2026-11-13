# Buyer access — deployed 28 October 2026

**Later update:** non-verified users can now also bid and purchase won auctions. See [AUCTION_BUYER_ACCESS_RELEASE.md](AUCTION_BUYER_ACCESS_RELEASE.md) for the current behavior, deployment and verification. The auction restrictions below describe this earlier release.

Authenticated users can purchase fixed-price listings, eligible lot items and accepted private offers without completing seller identity verification or Stripe Connect onboarding. Guests still need to sign in. Checkout address validation, stock, ownership checks, prices, fees, payment confirmation and seller payouts are unchanged.

Selling retains the exact previous requirements. In Didit mode: approved identity, fully onboarded Stripe payout account and complete private shipping origin. The legacy verification mode retains identity/address/bank approvals plus Stripe and shipping. This change does not mark buyers as verified sellers or create a Stripe connected account for them.

Auction bidding, raffle participation and trades retain their existing access restrictions. Separate bid/draw guards prevent the relaxed purchase policy from unintentionally allowing those actions. Their checkout and participation validations remain in place.

## Source and scope

Use `D:/CardoraGit/Cardora-scanner-live` for this release. The three backend files here matched the live baseline exactly before the changes. **The older backend at `D:/CardoraGit/Cardora/backend` is not a deployment source for this change.** Its vendor directory was reused only by the isolated local regression harness; its application source was not deployed or overwritten.

Backend changes are limited to:

- `backend/app/Services/MarketplaceAccessService.php`: independent buy/checkout capability; unchanged seller predicates; dedicated participation guards and action-specific messages.
- `backend/app/Http/Controllers/Api/AuctionBidController.php`: call `assertCanBid`.
- `backend/app/Services/DrawEntryService.php`: call `assertCanJoinDraws`.

Frontend uses the capability for each action instead of treating seller activation as a prerequisite for every action. Updated Greek/English messages in Navbar, Profile and Verification clarify the distinction. Product detail uses the separate auction capability. Existing colors, branding, layout, payment implementation and seller onboarding remain in place.

New regression tests: `frontend/tests/marketplace-access.test.js` and `frontend/tests/buyer-access-integration.test.js`; run `npm run test:buyer-access` in the frontend directory.

## Validation

Evidence directory: `D:/Cardora/RuntimeValidation/buyer-access-20261028`.

- Isolated Laravel application using a snapshot of the live source, SQLite `:memory:`, fake mail/jobs and blocked external HTTP: **550 assertions passed across 48 differential account-state combinations plus HTTP/checkout checks**. Seller and participation capabilities are compared to the exact original live service. Unverified users can add to cart and create an order/checkout request; unverified sellers and guests remain blocked. Ownership, stock and shipping validations still reject invalid requests. Every Stripe onboarding flag remains required for sellers.
- Actual Stripe SDK checkout request intercepted locally: buyer email used, no buyer connected account or `Stripe-Account` header, seller/order associations retained. **No real Stripe Checkout Session, payment, shipment or customer notification was created.** This is not a real payment settlement test.
- 2 new buyer-access tests, 3 existing session tests, 48 SEO/render tests and 6 origin tests passed. Production build passed. Scoped ESLint: zero errors; three pre-existing Hook dependency warnings in untouched effects remain.
- Preview HTTP checks passed for Greek/English public pages, fixed-price listing and private profile shell. A preview product page was inspected in the browser; further screenshot capture timed out. Authenticated buying behavior was exercised through real React providers with controlled test responses, not through a real customer's browser account.
- Live installed PHP classes passed 8 additional synthetic, unsaved-model combinations without application database queries or provider calls.
- Production: **31 HTTP audit requests, zero failed assertions**. Guest POST requests to cart, checkout and listings return 401. Served frontend JavaScript exactly matches the tested build. Existing bridge configuration and backend proxy deployment are unchanged.

## Deployment and rollback

Previous Pages production: `3e2d889d-0b01-4c86-8a05-a0e172944342`.

New Pages production: `462e3964-08bb-4b9c-b83f-0bb984663eaa`; preview: `ea3c6805.cardora-frontend.pages.dev`.

New private renderer release: `84e2a67376177e4d`. The previous renderer `f16d126301346499` is retained. The deployment topology in `PUBLIC_RENDER_DEPLOYMENT.md` remains unchanged.

Private backend stage and exact backups: `/home/u912666299/cardora-private/buyer-access-20261028`. `manifest.json` records before/after hashes. The CLI installer validates all target paths and hashes and replaces only the three listed PHP files. A transitional service preserves the previous buying policy while participation callers are updated, then the final service enables purchases. No migration, configuration, route or payment-service replacement was needed.

For rollback, check that the current Pages production is still the new deployment above, then restore the previous Pages deployment through Cloudflare. Run `/opt/alt/php82/usr/bin/php /home/u912666299/cardora-private/buyer-access-20261028/install.php --rollback` only if this backend release is still current: its hash guards reject later modifications. Keep both renderer releases available while either Pages release may reference them. Recheck public pages and authenticated behavior after rollback.

No RunPod operation, paid plan, new API credential or Hostinger support message was involved. Users with an already-open page should reload to obtain the new capability summary and frontend.
