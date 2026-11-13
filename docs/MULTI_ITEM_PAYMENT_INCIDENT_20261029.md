# Two-book payment incident — 29 October 2026

## Confirmed facts

Stripe captured **EUR 55.75** for checkout batch `97cd4e57-8ae5-44ed-a376-a99d3b26a4e5`, buyer 36 and seller 10. The provider's session, intent and expanded line items identify two orders:

| Order | Listing | Item | Shipping | Buyer-paid low-value fee | Order total | Seller pending credit |
| --- | --- | --- | --- | --- | --- | --- |
| 41 / CDR-20261029132106-FSWB | 318 | EUR 4.00 | EUR 3.00 | EUR 0.75 | EUR 7.75 | EUR 4.00 |
| 42 / CDR-20261029132106-6FF3 | 316 | EUR 45.00 | EUR 3.00 | — | EUR 48.00 | EUR 42.75 |

At inspection, order 41, its item 42 and escrow record were absent from the database. No pending credit or order confirmation notification existed for it. Order 42 was paid/pending release and had a BOX NOW label. Stripe explicitly retained `order_ids=41,42` and both original order numbers. No refund or dispute was present at recovery verification.

The exposed cancellation endpoint hard-deleted an individual pending order and its escrow, restored stock and did not expire the associated shared Stripe Checkout session. The checkout return page invoked that endpoint for the primary order only. This is a reproducible mechanism matching the missing primary-order evidence, but **the initiating user/action is not established by an access audit**. Do not state that the customer or seller definitely cancelled it.

A second defect cast Stripe SDK metadata objects with `(array)`, which exposes internal object fields instead of their actual metadata. A payment-intent-first event could therefore fail to resolve orders before intent IDs were saved. The later Checkout event could resolve the surviving order by session ID and mark itself processed without detecting the absent order.

## Deployed repair

Only these three backend files changed, against fresh live snapshots:

- `app/Services/StripeMarketplaceService.php`: normalize SDK metadata through `toArray()`, compare expected batch IDs with actual orders and reject missing members; enforce buyer consistency; expire an open/unpaid Checkout session before cancelling every order in that batch; retain records and inventory idempotency; fail closed on provider errors or payment/expiry races.
- `app/Http/Controllers/Api/StripeWebhookController.php`: use normalized metadata for failure and expiry handling.
- `app/Http/Controllers/Api/OrderController.php`: cancellation is buyer-only and delegates to the guarded batch operation; no hard deletion. A seller cannot cancel another seller's orders through a shared checkout.

A declined card attempt remains pending for retry; only confirmed session expiry cancels/restores stock. This prevents the normalized failure webhook from cancelling an otherwise retryable Checkout session.

Private hash-guarded release stages:

1. `/home/u912666299/cardora-private/multi-item-safety-20261029`
2. `/home/u912666299/cardora-private/multi-item-retry-guard-20261029` (final deployed revision)

No frontend deployment, migrations or RunPod changes. Backups and installation manifests are retained privately. Code rollback, if needed and hashes still match, must run the retry-guard installer with `--rollback`, then the first installer. **Do not roll back the recovered customer accounting by deleting the restored order.** That requires a separately reconciled recovery plan.

## Customer accounting restored

Order 41 was reconstructed with its original ID/number/item ID from verified Stripe metadata and price breakdown. It is paid/pending release with one escrow record and exactly EUR 4.00 additional seller pending credit. Its listing inventory is zero/sold. Both orders now total EUR 55.75 and have the actual shared Stripe charge reference. Recovery is marked in metadata; private pre-change evidence and transaction guards prevent accidental reapplication. The item's condition is explicitly marked as reconstructed from the current listing, not a recovered historical snapshot.

No new charge, refund, transfer or payout was created. Order 42's existing label is preserved. The additional EUR 4.00 reserve was reconciled separately through the existing platform reserve service, guarded against any difference other than EUR 4.00 (EUR 205.00 → EUR 209.00 held minimum across all pending orders).

**Resolved with explicit merchant authorization:** reuse the existing parcel for these two books only, since buyer and seller are the same. Order 41 now has order 42's existing tracking, label reference, shipping destination and current shipping state. Both records retain explicit shared-parcel audit markers; the recovered shipping blocker is removed. No new label, charge or release was created. Normal checkout still creates separate orders and label jobs for each cart line.

An additional targeted deployment updates `BoxNowWebhookController` to apply a signed parcel event to every matching BOX NOW order instead of only the first. This keeps delivery timestamps and the release window aligned for the authorized shared parcel. Carrier filtering and signature validation remain unchanged. Private hash-guarded stage: `/home/u912666299/cardora-private/shared-shipment-20261029`.

The missing order 41 confirmation was sent to both buyer and seller, accepted by the mail transport at 14:42:43 and 14:42:51 UTC on 29 October 2026 respectively. Each explains the second book, price breakdown, shared parcel and no new payment. Send markers and private attempt records prevent accidental duplicate recovery emails. Transport acceptance does not establish inbox delivery. No push notification was sent.

## Verification

Evidence: `D:/Cardora/RuntimeValidation/multi-item-incident-20261029`.

- **55 local checks passed**, using fresh live Laravel source, in-memory SQLite, actual HTTP cancellation endpoint, real Stripe SDK objects and intercepted provider calls. Cases cover two items from the same/different sellers, both confirmation emails, independent label job IDs, both escrow/ledger records, webhook replays, buyer/seller cancellation authorization, inventory restoration exactly once, provider outage, payment/expiry race, retry after declined card, and an absent primary order.
- **13 read-only live checks passed** after accounting recovery, including deployed hashes, both order/escrow records, EUR 55.75 reconciliation, EUR 46.75 seller pending credits, actual charge linkage and preservation of the existing label. Separate reserve verification confirms the EUR 4.00 correction.
- **66 local checks passed** after the shared-parcel change (the original 55 plus 11): invalid signatures rejected, locker arrival updates both without starting release, delivery updates both with the same release deadline, duplicate events preserve that deadline, unrelated parcels/carriers remain unchanged, unknown parcels are acknowledged and no funds are transferred by the event itself. Evidence: `shared-shipment/test-results.json`.
- **33 read-only live checks passed** after the authorized shipment repair and email sends: deployed controller hash, original tracking/reference on both orders, same destination, explicit linkage markers, unchanged amounts/charge/transfer/release fields, both escrows and exactly two seller credits, and both mail transport acceptance records. Evidence: `shared-shipment/live-verification.json`.
- These checks do not claim carrier delivery, actual new label issuance, receipt of recovery email, concurrent MySQL load testing or completed seller payout. The previous final buyer journey test stopped at unpaid Stripe Checkout and did not cover post-payment fulfillment or cancellation; this incident adds that missing coverage.

Primary Stripe reference for expiration semantics: https://docs.stripe.com/api/checkout/sessions/expire .
