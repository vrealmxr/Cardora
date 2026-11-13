# Order contents and sold-item conversation context

The inbox resolved product titles through the active public catalogue, so a sold listing could appear as `-`. Participant-scoped bootstrap conversations now include explicit listing context (title, photo, descriptive fields and offer availability). The inbox list and conversation panel use that context independently of the public catalogue. Sold items cannot invite new private offers through this context.

Each order's bootstrap includes its individual item records. Title, condition, quantity and purchase price come from the saved order item; current linked product media and descriptive fields supplement them. Historical image/description snapshots were not retroactively created. Missing media has an explicit fallback. Internal moderation fields and shipping/contact addresses are not included in the item context.

Order cards now offer **Λίστα παραγγελίας / Order items**, an accessible expandable section with photos (thumbnail switching where available), item identity, quantity, unit/line price, condition, available product details and the order's products/shipping/fees/total breakdown. Both buyer and seller see it; unrelated users remain excluded by the existing participant-scoped queries. Existing styling is retained.

## Verification and deployment

- 72 isolated PHP checks: original 55 payment regressions plus 17 item/context checks, including both participants, sold listings, purchase snapshots overriding edited listing price/condition, photos/details and unrelated-user exclusion.
- React integration tests exercise the actual order card expansion/collapse for both buyer and seller and the inbox with an empty public catalogue; the messaging identity regression also passes.
- 17 read-only live checks verify orders 41/42, their EUR 4/EUR 45 purchase prices and photos, correct conversation context and participant isolation.
- Scoped ESLint passes. Production build succeeds. Preview visual checks at 1440px and 390px verify actual item photos, title, no horizontal overflow and no browser errors. Fixtures exclude customer messages and contact data.
- Final **live Chrome** test as the actual seller opened the order-items section, verified the real image and EUR 4/EUR 7.75 values, then navigated to conversation 14 and verified the title. No message was sent; the temporary five-minute diagnostic token was revoked in `finally`.
- Evidence: `D:/Cardora/RuntimeValidation/order-items-20261029`, including `browser-result.json`, `live-verification.json`, `visual-results.json` and desktop/mobile screenshots.

Backend scope: only `app/Services/MarketplaceBootstrapService.php`, deployed against a fresh live hash via `/home/u912666299/cardora-private/order-items-20261029/install.php`; private backup and `--rollback` retained. No migrations, payment/shipping changes, RunPod actions or new paid services.

Frontend preview `3c0700ac`, renderer `492e05bd4b9fbd34`, production Pages prefix `4df85563`. Exact production ID in evidence `cloudflare-after.json`. Previous Pages `d80c4ccd-a87e-49d2-86e9-ba8f6f575015`, renderer `1f6dbc0fc823dbaa` retained. For rollback restore previous Pages first, then the hash-guarded backend if needed. Keep renderer releases for existing deployments.
