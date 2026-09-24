# Gameplay backfill — final frozen validation report (v1, applied)

**Status: APPLIED to production, 2026-09-24.** `gameplay_data` is now populated for all 4 games.
Tagged `gameplay-data-v1` (separate from the canonical catalog tags — `magic-catalog-v1` /
`yugioh-catalog-v1` / `onepiece-catalog-v1` do not exist yet, that's a separate pending task).
Frontend/scanner were **not** touched — they still read the pre-existing schema.

## Pre-apply safety

- Full `mysqldump --single-transaction --quick --routines --triggers`, gzip-compressed:
  `storage/app/db-backups/pre-gameplay-backfill-20260924-122727.sql.gz` (32.6MB).
- `gzip -t`: OK. Dump ends with a clean `-- Dump completed on 2026-09-24 12:27:31` marker
  (not truncated), 1,172,772 lines.
- Backup SHA256: `96290ad570d6fa03d6a5aa5f6a4f49046b109d7a05a7a542135816e2e2e787f6`.
- All 4 snapshot raw files re-hashed against this directory's git-committed manifests
  immediately before apply: Magic/Pokémon/Yu-Gi-Oh! matched byte-for-byte; One Piece verified
  via local tarball hash match + production's extracted file count (4844, matching every prior
  dry-run's `source_objects_total`).

## Per-game apply results (ran individually, in order, each with its own post-validation)

| Game | Written | Null (intentional gap) | Changed | Unresolved conflicts | Malformed | Expected | Match |
|---|---|---|---|---|---|---|---|
| Magic | 109254 | 0 | 0 | 0 | 0 | 109254 | ✓ |
| Pokémon | 21267 | 42 | 0 | 0 | 0 | 21267 | ✓ |
| Yu-Gi-Oh! | 38320 | 255 | 0 | 0 | 0 | 38320 | ✓ |
| One Piece | 2785 | 14 | 0 | 0 | 0 | 2785 | ✓ |

**Total written: 171,626** (matches the expected total exactly).

After every single apply, all of `binder_cards` (382,489), `binder_card_variants` (241,690),
`binder_sets` (3,749), `binder_card_external_ids` (538,446), `binder_releases` (33),
`binder_card_release_memberships` (942), `users` (17), `orders` (17), `products` (361), and
`binder_user_cards` (20) were re-checked and confirmed **byte-identical to the pre-apply
baseline** — no row created, deleted, or touched outside `binder_cards.gameplay_data`. Each
earlier game's rows were also re-checked intact after every subsequent game's apply.

One Piece's 14 null rows were explicitly verified to be exactly the 14 `onepiece:don:*` card
keys (`null_card_keys_are_all_don=yes`) — not a mismatch masquerading as the expected gap.

## Post-apply idempotency (re-ran `--game=all --dry-run` after the last apply)

A real bug surfaced and was fixed here: the first idempotency re-run showed
`already_present=0` / `would_change=<full count>` for every game, because `resolved_at`
(intentionally "now" on every resolve) was included in the raw JSON-string comparison, so a
rerun would have reported "changed" forever. Fixed in `GameplayBackfill::contentChanged()` to
compare the envelope excluding `resolved_at`. After the fix:

```
                already_present  would_change  would_be_added   without_gameplay (unchanged)
MAGIC           109254           0             0                0
POKEMON         21267            0             0                42
YUGIOH          38320            0             0                255
ONEPIECE        2785             0             0                14
TOTAL           171626           0             0                311
```

`already_present` totals exactly 171,626 as expected. No automatic "filling" of the 311
intentional gaps was attempted — they remain `gameplay_data = NULL` by design.

## Post-write spot checks (read back from the actual stored production rows)

- **Magic** `magic:scryfall:6904ea20-...` (Delver of Secrets // Insectile Aberration): stored
  `data.faces[]` present with both faces' fields nested; top-level `mana_cost`/`power`/
  `toughness`/`oracle_text` correctly absent.
- **Yu-Gi-Oh!** Decode Talker (Link Monster, `yugioh-duel-devastator-dude-en023`): stored
  `data` has `linkval`/`linkmarkers`/`atk`, no `level`/`def`/`rank`/`scale`.
- **One Piece** `onepiece-en-st-01-st01-001` (Leader): stored `data.life = 5`, no `cost` key.
  `onepiece-en-st-01-st01-002` (Character): stored `data.cost = 2`, no `life` key.
- **One Piece** `onepiece-en-op14-eb04-eb01-023` (Edward Weevil): stored `data.power = 6000`
  (not the conflicting 8000), `counter` correctly absent; `provenance` records the applied
  override exactly.
- **Pokémon** Charizard/Pikachu: stored `attacks`/`abilities`/`weaknesses`/`resistances`
  correctly nested; `evolves_from` correctly absent on Basic-stage Pikachu.

---

## Original dry-run reconciliation (unchanged from the pre-apply review)

All four games passed every hard-FAIL condition
(`ambiguous_matches`, `unresolved_gameplay_conflicts`, `cards_that_would_be_created`,
`variants_that_would_be_created`, `canonical_identity_changes` are all `0`).

```
MAGIC        canonical=109254  source_objects=118610  matched=109254  without_gameplay=0     unmatched_source=9356   conflicts=0  ambiguous=0
POKEMON      canonical=21309   source_objects=23747   matched=21267   without_gameplay=42    unmatched_source=2480   conflicts=0  ambiguous=0
YU-GI-OH!    canonical=38575   source_objects=38458   matched=38320   without_gameplay=255   unmatched_source=129    conflicts=0  ambiguous=0
ONE PIECE    canonical=2799    source_objects=4844    matched=2785    without_gameplay=14    unmatched_source=0      conflicts=1  ambiguous=0
```

(`gameplay_conflicts_resolved=1`, `overrides_applied=2` for One Piece — see EB01-023 below.
`gameplay_data_already_present=0` / `would_be_added=<matched count>` / `would_change=0` for
every game, since no row has ever been backfilled yet.)

## Coverage reconciliation — every unmatched/missing record explained

**Magic — 9356 unmatched source objects**: 100% `digital=true` (Arena-only cards, never
physical, correctly out of scope). 0 physical unmatched.

**Pokémon — 42 canonical cards without gameplay**: exactly the pre-existing supplemental gap
(`rc`=25 + `sp`=10 + `wp-*`=7 = 42, matching `../tcgdex-supplemental/README.md`) — cards
manually researched because TCGdex's own source never had card-level data for them, so no
gameplay snapshot could either. **2480 unmatched source objects**: verified via each raw
object's own `set.serie.name` — all 15 distinct set IDs are `"Pokémon TCG Pocket"`, the digital
app, out of scope for the physical catalog.

**One Piece — 14 canonical cards without gameplay**: exactly the 14 verified supplemental
DON!! cards (`../onepiece-supplemental/supplemental_don_cards.csv`) — DON!! cards are absent
from the primary source entirely, so gameplay data doesn't exist for them by construction. Left
`gameplay_data = NULL` in v1, not synthesized.

**Yu-Gi-Oh! — 255 canonical cards without gameplay / 129 unmatched source objects**: every
record traced to one of two causes, zero unexplained:
1. **Ambiguous `set_code`, shared by 2+ different canonical sets** (251 of 255; 123 of 129) —
   the *original* canonical importer's own `nonUniqueCardCodesSkipped` logic deliberately never
   recorded an external_id for these, to avoid a wrong match. Verified concretely: `LART-EN010`
   genuinely belongs to both "The Lost Art Promotion series" and "The Lost Art Promotion J"
   canonical sets; `MRD-EN051` belongs to both "Metal Raiders" and its "25th Anniversary
   Edition" reprint. Pre-existing, not introduced by gameplay enrichment.
2. **Supplemental/manual-research cards** (4 of 255 canonical-side; 6 of 129 source-side —
   `PCY-001..005` + `KACB-EN001`) — from `../ygoprodeck-supplemental/`, never linked to a
   primary-source `set_code` by design; some of their codes are *also* independently listed in
   today's live YGOPRODeck data, which is why they surface on both sides.
3. **Genuinely new post-freeze additions: 0.** No canonical YGO card that should have matched
   via the primary source failed to — zero blockers.

## Spot checks (real generated envelopes, verified 2026-09-24)

- **Magic** `magic:scryfall:6904ea20-...` (Delver of Secrets // Insectile Aberration): top-level
  `mana_cost`/`power`/`toughness`/`oracle_text` correctly **absent** (not null), `data.faces[]`
  present with both faces' fields nested correctly.
- **Yu-Gi-Oh!** Decode Talker (Link Monster): `data` has `linkval`/`linkmarkers`/`atk`, no
  `level`/`def`/`rank`/`scale` keys at all — confirmed frame-type-aware omission, not raw
  passthrough (the raw YGOPRODeck response itself returns `level:0, def:null` for this card).
- **One Piece** `onepiece-en-st-01-st01-001` (Monkey.D.Luffy, Leader): `data.life = 5`, no
  `cost` key. `onepiece-en-st-01-st01-002` (Usopp, Character): `data.cost = 2`, no `life` key.
- **One Piece** `onepiece-en-op14-eb04-eb01-023` (Edward Weevil): `provenance` records the
  applied override (`cost`, `power`, `counter`, `effect`); resolved `data.power = 6000` (not the
  conflicting 8000), `counter` correctly absent (override value was empty).
- **Pokémon** Charizard (`pokemon-base1-4`, Stage2): `evolves_from`, `attacks[]` (with nested
  `cost`/`damage`/`effect`), `abilities[]`, `weaknesses[]`, `resistances[]` all present and
  correctly nested. Pikachu (`pokemon-base1-58`, Basic): `evolves_from` correctly **absent**
  (not applicable to a Basic-stage card), `abilities`/`resistances` correctly `[]` (applicable,
  confirmed empty by the source, not "not applicable").

## Known scope limits (honest, not hidden)

- Only One Piece has a real conflict-detection/override pipeline with history (the same one
  `ImportOnePiece` uses, via `OnePieceGameplayNormalizer`) — Magic/Pokémon/Yu-Gi-Oh! never
  aggregate multiple source objects per canonical card by construction (1:1 or 1:many-but-
  identical-stats matching), so their `severeFields()` are empty and conflict detection is a
  no-op for them, not an untested code path pretending to be tested.
- Yu-Gi-Oh!'s `source_version` cannot be verified byte-for-byte on a future re-run (no upstream
  bulk export exists) — re-verification there means re-running this reconciliation, not a
  checksum match.
