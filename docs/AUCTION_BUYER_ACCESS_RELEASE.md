# Auction buyer access — 28 October 2026

Deployed to cardora.gr. This supersedes the auction restriction recorded in `BUYER_ACCESS_RELEASE.md`.

Authenticated users can bid and purchase an auction they win without seller verification or a Stripe connected account. Selling retains all previous verification, Stripe onboarding and shipping requirements. Raffle/trade policies are outside this change.

## Why checkout needed a scoped addition

The previous implementation rejected every auction in the cart, cart sanitizer and order checkout service, including verified buyers. The frontend also explicitly rejected adding auctions to the cart. Changing `can_bid` alone would therefore allow bidding but would not deliver the requested purchase flow.

The new flow is **auction ends → winning bidder adds it to cart → existing shipping/Stripe checkout at the winning bid**. The winner is validated again under a listing row lock at checkout. Quantity is one auction; its available inventory is reserved once. Cancellation restores the reserved inventory while preserving the winner and expired end date, allowing the same winner to retry. The reserve must be met and a matching submitted bid must exist. Client price, bid status, timestamp and selection metadata cannot override these server decisions.

This does not add an early buyout flow: the pre-existing buyout-price display remains informational. No real-time bidding infrastructure, auction closing scheduler, new payment provider or payment/payout rules were added.

## Targeted source changes

Source: `D:/CardoraGit/Cardora-scanner-live`. Backend edits were prepared against exact live-file snapshots, not an older checkout.

- `MarketplaceAccessService`: bid access follows buy access; seller and raffle predicates unchanged.
- New `AuctionPurchaseService`: validates auction availability, seller readiness, ownership, timing, reserve, winner and quantity.
- `AuctionBidController`: validates against the locked current listing; blocks self-bids and bids before start/after end; server controls bid status/time.
- `CartController`, `CartSanitizerService`, `OrderCheckoutService`: allow and revalidate only the qualifying winner; reuse existing checkout, fees, shipping, Stripe integration and stock restoration.
- `MarketplaceBootstrapService` and `PublicPageDataService`: expose auction start time and winning bidder ID in the existing public auction DTO. No additional profile/contact fields are exposed.
- Frontend action gates/messages, winner purchase controls and auction quantity selector; successful bids refresh the listing. Existing design and colors retained.

No database migration, route, configuration, Stripe service or seller-onboarding implementation was changed. Updated public DTO allowlist is in this checkout; older local backend directories are not an authoritative deployment source.

## Verification and limits

Evidence: `D:/Cardora/RuntimeValidation/auction-access-20261028`.

- **535 backend assertions passed**, including 48 account-state combinations against the original seller/raffle predicates. Actual Laravel HTTP routes exercised in an isolated SQLite database.
- An unverified buyer without a payout account can bid, win, add to cart and reach Stripe checkout at the winning amount. Negative cases cover guest access, own auction, minimum bid, timing, reserve, non-winner access, quantity tampering, direct-checkout bypass, duplicate checkout and forged metadata.
- Cancellation/retry tested through the existing order service; it does not reopen ended bidding or unlock selling.
- Stripe SDK requests intercepted locally, fake mail/jobs and no external HTTP. **No live bid, customer order, payment, shipping label or notification was created for testing.** Actual payment settlement and simultaneous MySQL contention were not tested; production code uses the existing transaction mechanism and a row lock for bids and auction checkout.
- 3 buyer/auction frontend tests passed, including the actual ProductDetailPage showing the unverified winner's purchase action and adding to cart. 3 session tests, 48 SEO/render tests and 6 origin tests passed. Production build passed. Scoped lint: zero errors; 3 pre-existing Hook dependency warnings remain.
- Preview public/private-shell checks passed. Live installed policy passed 8 synthetic unsaved-model combinations. Production audit: **31 requests, zero failed assertions**. Guest cart/checkout/listing/bid writes still return 401. Served JavaScript hash matches the tested build.

Run frontend regressions with `npm run test:buyer-access`. Backend harness and exact baseline/diffs are retained in the evidence directory (`test-auction-access.php`, `test-bootstrap.php`); it uses SQLite `:memory:` and does not load production credentials.

## Release and rollback

Pages production: `08aece6b-7bf5-4afb-92ee-27f6bdd91618`.

Previous production: `462e3964-08bb-4b9c-b83f-0bb984663eaa`.

Preview: `e7aba62b.cardora-frontend.pages.dev`. Renderer: `a2c32bc2acf5fa90`; previous renderer `84e2a67376177e4d` retained.

Private backend stage: `/home/u912666299/cardora-private/auction-access-20261028`. The installer checks exact file hashes, saves backups and atomically replaces only the 8 scoped files. Helper/validation logic is installed before opening bid access. Existing bridge/proxy configuration remains unchanged.

Rollback only while these releases remain current: restore the previous Pages deployment, then run `/opt/alt/php82/usr/bin/php /home/u912666299/cardora-private/auction-access-20261028/install.php --rollback`. Hash guards reject subsequent source changes. The harmless new helper remains for any in-flight requests. Do not roll back database records: if real auction orders have already been created, first review their pending payment/reservation lifecycle before disabling further auction checkout. Retain both renderer releases.

No RunPod operations or paid service changes. Existing open pages should be refreshed to obtain the updated frontend and access summary.
