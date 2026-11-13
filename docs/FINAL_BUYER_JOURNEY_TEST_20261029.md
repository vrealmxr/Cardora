# Final unverified buyer journey check — 29 October 2026

Result: **42 backend integration assertions passed**, two real Stripe sandbox Checkout pages verified in the browser, and both test sessions expired unpaid after inspection. No application or production configuration changes were required.

## Scope

A fresh copy of the currently deployed Laravel application was downloaded over SSH, without its environment file, database or customer data. It ran locally against SQLite `:memory:`. The test exercised actual registration, password login, authentication, bidding, cart, checkout and listing HTTP routes through Laravel's HTTP kernel. Tokens came from registration/login, not a fabricated authenticated identity.

Stripe Checkout creation used the actual Stripe API with the existing local **test** credential. A transport guard allowed only test Checkout session creation. Fixture orders used negative IDs, unique checkout batch IDs and `QA-LOCAL-` order numbers. Mail, notifications and jobs were intercepted locally. Shipping and seller data were isolated fixtures; no shipping API or identity-provider operation was performed.

This is **not a complete browser click-through of the production Cardora login and checkout UI**. The Cardora portion was exercised through real application HTTP handlers in isolation; the resulting hosted Stripe pages were inspected in the browser. Production routing/cookies, real shipping validation, payment submission, payment-success webhooks, seller transfers and concurrent MySQL locking are outside this test. Existing frontend and production deployment checks are documented in `AUCTION_BUYER_ACCESS_RELEASE.md`.

## Results

Two separate users were registered, logged out and signed in again using their passwords. Both had no email verification, no identity verification and no Stripe connected account. Incorrect passwords were rejected, access summaries enabled buying/bidding but disabled selling, and logout invalidated the issued token.

| Flow | Application result | Stripe sandbox result |
| --- | --- | --- |
| Fixed price | Item added to cart and retained on refresh; checkout returned HTTP 201 | €20 item + €3 fixture shipping = **€23**; Sandbox badge and payment choices displayed |
| Auction | €27 bid accepted; auction expiry simulated locally; buyer confirmed as winner; winner added auction to cart and reached checkout | €27 winning bid + €3 fixture shipping = **€30**; Sandbox badge and payment choices displayed |

For **both users**, attempts to create either a fixed-price listing or an auction returned **HTTP 422 with the marketplace seller-access error**. No seller listing or payout account was created. Seller verification requirements remained enforced on the server.

No payment details were entered and Pay was never submitted. Both sessions were subsequently expired via the test API and retrieved again to verify:

- `livemode: false`
- `status: expired`
- `payment_status: unpaid`
- `payment_intent: null`

The sandbox merchant display name is **VRealm PC sandbox**; this is the existing test account branding, not a change to Cardora's production branding.

No real customer account, bid, order, charge or shipping label was created. Existing test-mode webhooks may record these test-session events; their unique session/batch identifiers do not reference production orders. No RunPod operations or paid service changes were made.

## Evidence

Local evidence directory: `D:/Cardora/RuntimeValidation/final-buyer-journey-20261029`.

- `live-source-hashes.json`: fresh source snapshot hashes.
- `journey.php` and `test-bootstrap.php`: isolated integration harness.
- `journey-results.json`: all 42 checks and HTTP response codes.
- `fixed-price-stripe-test.png`, `auction-stripe-test.png`: actual hosted Stripe sandbox pages.
- `close-test-sessions.php`, `cleanup-results.json`: narrowly scoped expiry and final unpaid state verification.

Harness execution creates two new test Checkout sessions; it should not be run casually. After any intentional rerun, inspect and expire only the sessions recorded by that run. Credentials are read from the existing local environment file and are not included in the evidence report.
