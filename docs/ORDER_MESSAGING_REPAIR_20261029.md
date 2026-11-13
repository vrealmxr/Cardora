# Order participant messaging repair — 29 October 2026

## Follow-up: actual bootstrap identity

The first frontend release incorrectly used `Number(order.id)`. The real orders bootstrap puts the human-readable `CDR-…` number in `id` and the database key in `databaseId`. Consequently JSON serialized the resulting `NaN` as `null`, and validation rejected the request. The original frontend test used a numeric `id` and missed this contract mismatch; the backend checks alone did not establish the UI payload was correct.

Corrected the frontend to prefer `databaseId`, retain numeric `id` compatibility, and reject invalid/nonpositive IDs locally before sending. Expanded the integration harness to render the actual `OrderCard`, click its seller message button with a realistic bootstrap record, assert `{order_id: 41}`, and verify navigation to `/minymata?conversation=77`. Invalid identity and ordinary listing inquiry behavior are also checked. The buyer-access integration still passes.

Twelve live checks now obtain the real bootstrap projection for orders 41/42 and feed its `databaseId` into the deployed controller, with all temporary conversation records rolled back and no messages or notifications. Evidence: `D:/Cardora/RuntimeValidation/order-messaging-identity-20261029`. Follow-up renderer: `1f6dbc0fc823dbaa`; preview: `53119891`. No further backend change was needed.

## Initial repair

Follow-up production deployment: `d80c4ccd-a87e-49d2-86e9-ba8f6f575015`. Actual served application verified against the tested build on cardora.gr in both languages, with original shipping artwork preserved.

The seller's order-card button sent only `listing_id` to the buyer-to-seller inquiry endpoint. When no matching conversation was already cached, the controller treated the seller as a prospective buyer and rejected contacting their own listing with 403. Cached existing conversations could open without that request.

The order action now sends `order_id`. The backend verifies the caller is the actual buyer or seller, derives the listing from the order item, and resolves both participants from the order. It refuses mismatched listing IDs, missing or ambiguous order items, and callers outside the order. Ordinary listing inquiries keep their existing behavior. Initial-message sender attribution and recipient notifications now work in both directions. Opening an empty conversation sends no message or notification.

Scope: `frontend/src/context/MarketplaceContext.jsx`, `backend/app/Http/Requests/StoreConversationRequest.php`, and `backend/app/Http/Controllers/Api/MessageController.php`. No schema/configuration, payment, shipment, verification, RunPod or billing changes.

## Verification

- 73 isolated PHP checks: 55 payment regression checks plus 18 messaging checks. Actual HTTP requests cover seller creation, buyer reuse, repeated clicks, outsider create/read/send denial, listing mismatch, missing order/context, normal listing inquiries and correct message recipients. All email/network calls were intercepted.
- React-provider integration test `frontend/tests/order-messaging.test.js` verifies order identity is sent even when product identity differs or is absent, and ordinary inquiries still send listing identity.
- Ten live checks against actual orders 41 and 42 verified both participant directions, correct listings and no message/notification creation. Temporary conversation rows were rolled back in a database transaction. No real message was sent.
- Frontend production build and six origin tests passed. Scoped ESLint has zero errors and two existing `fetchBootstrap` hook-dependency warnings in MarketplaceContext; unrelated hooks were not changed.
- Preview `c9ee71bd` verified in Greek and English, including actual served app equality and preservation of official shipping partner artwork. Renderer release `9f75ef195febb7d1` uploaded privately and hash verified.
- Production Pages `3765100e-dc15-4936-b716-05eb26168409` published and verified on `https://cardora.gr` in both languages. The actual served app matches the tested build.

Evidence: `D:/Cardora/RuntimeValidation/order-messaging-20261029`.

Backend release and backups: `/home/u912666299/cardora-private/order-messaging-20261029`. Its installer checks exact before/after file hashes and supports `--rollback`. Previous Pages production: `a3f3e29a-4fd6-4599-83e6-bf1271643b05`, renderer `3e565ac871051013`. If rollback is needed, restore the previous Pages version before rolling back the backend so the order-aware frontend never targets the older API. Keep both renderer releases available.
