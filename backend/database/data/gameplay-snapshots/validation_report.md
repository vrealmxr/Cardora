# Gameplay backfill dry-run — validation report

Produced by `php artisan cardora:gameplay-backfill --game=all --dry-run` against production,
2026-09-24. All four games pass every hard-FAIL condition
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
