# One Piece TCG supplemental dataset — DON!! cards

DON!! cards are entirely absent from the primary source (`punk-records`/`vegapull`, itself scraped from Bandai's official card-list site, `en.onepiece-cardgame.com/cardlist/`) — that site's card list never includes DON!! cards, since they're not individually browsable/rarity-tiered like normal cards. This directory is the hand-researched, provenance-tracked gap-fill for them, mirroring the pattern used for `tcgdex-supplemental/` and `ygoprodeck-supplemental/`.

## Files

- **`supplemental_don_cards.csv`** — 14 verified canonical DON!! cards. Every row was verified against an official Bandai product page **and** a second independent source (a distributor listing, retailer product page, or multiple converging community card-database sites) — never inferred from artwork alone.
- **`supplemental_don_variants.csv`** — 16 verified treatments (Standard / Normal / Alt-Art / Super Alt-Art) across those 14 cards.
- **`supplemental_don_release_memberships.csv`** — variant-level release memberships (`binder_card_release_memberships.variant_id`) tying each verified variant to the real product it ships in (PRB-01, PRB-02, or Heroines Special Set / EB-03).
- **`unresolved_standard_don_designs.csv`** / **`unresolved_special_don_designs.csv`** — backlog, **not imported**. Counted in the validation report (`unresolved_standard_don_designs`, `unresolved_special_don_designs`) so the gap stays visible for future enrichment instead of disappearing.

## Identity

DON!! cards have **no official collector number** anywhere in any source checked. `collector_number` is left empty (→ `NULL` after import) rather than inventing one. Canonical identity is instead a deterministic key built from the verified design identity and its release:

```
onepiece:don:{release}:{design-slug}
onepiece:don:{release}:{design-slug}:{treatment-slug}   (variant)
```

## What's verified vs. what's backlog

**Verified (14 cards / 16 variants, all in this dataset):**
- **PRB-01** — Ace, Luffy, Sabo (3 cards, 1 Standard variant each). Gold-foil tier confirmed to exist in the product but not attached to any specific one of these 3 by any source found — not fabricated as a variant.
- **PRB-02** — 10 named Super Alt-Art designs (Marshall.D.Teach, Monkey.D.Dragon, Monkey.D.Luffy Gear 4, Monkey.D.Luffy Gear 5, Yamato, Lim, Rob Lucci, Boa Hancock, Dr. Vegapunk, Foxy). Foxy alone is confirmed with both a Normal and a Super Alt-Art tier (2 variants); the other 9 are confirmed only at Super Alt-Art tier.
- **Heroines Special Set / EB-03** — 1 themed "Promotion DON!! Card" design, confirmed with Normal and Alt-Art tiers.

**Explicitly NOT imported, tracked as backlog instead:**
- **The generic/standard DON!! card** (`unresolved_standard_don_designs.csv`) — its existence in the physical game is certain (rulebook requires 10 per deck), but no source found confirms *which* specific English-market artwork/printing is actually used, vs. the 9 color variants shown in the official Asia-EN PDF potentially being JP-exclusive. Deliberately not created as an abstract/generic canonical card with no confirmed specific collectible object behind it.
- **~296 further special/promotional DON!! designs** (`unresolved_special_don_designs.csv`), split into:
  - Named-product remainders: 27 more PRB-01 designs (30 total confirmed, only 3 named), 20 more PRB-02 designs (30 total confirmed, only 10 named), 2 DP-12 designs (existence/count confirmed via official page + a distributor listing, 0 named).
  - ~247 designs from the official Bandai Asia-EN "DON!! Card List" PDF (`asia-en.onepiece-cardgame.com/pdf/don-cardlist.pdf`, 28 pages) that have **zero product/region labeling anywhere in the source document** (confirmed by directly reviewing pages 1, 2, 20, and 28 — no index or legend exists in the whole 28-page catalog). Region is explicitly unconfirmed for English applicability — the PDF is Asia-region-specific and no English-market equivalent exists (`en.onepiece-cardgame.com/pdf/don-cardlist.pdf` returns 404).

**Explicitly excluded, not backlog**: "Premium Card Collection -ONE PIECE DAY '26-" — initially thought to be an English product based on an imprecise summary, but confirmed during research to be **Japanese-market only** (Premium Bandai JP lottery, JPN-region listing). Not part of this dataset at all.

**Confirmed but not design-mapped**: SD-01 and DP-12 both confirmably include DON!! cards (official page + distributor cross-check), but without enough per-design identity to create card/variant rows — they exist as verified `binder_releases` rows (real products) but no DON!! variant is linked to them yet, per explicit instruction not to guess a design→product mapping without documentation.

## Source

- Official Bandai English product pages (`en.onepiece-cardgame.com/products/...`) — primary source for count/tier claims.
- Cross-check sources per product: onepiece.gg (PRB-01), Beckett/GameNerdz/TCGplayer/onepiece.gg/japan-figure/samuraiswordtokyo/ultimasupply (PRB-02, 7 converging sources), redcardgames.com (DP-12), p-bandai.com/us (Heroines Special Set, confirms English/US availability).
- Official Bandai Asia-EN "DON!! Card List" PDF — visual reference only, no per-design text labels; retrieved 2026-09-23, `Last-Modified: Fri, 21 Nov 2025`.
