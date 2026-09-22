# TCGdex supplemental dataset

Fills gaps the pinned TCGdex `cards-database` snapshot doesn't have card-level
data for (`upstream_missing_card_data`), for sets where TCGdex's own set
metadata confirms the set exists and its size, but its `cards` list is empty.

This is **not** used automatically for every gap — only for the specific
set_ids listed in `supplemental_sets.csv`, researched and manually verified
one set at a time. A set with no row here that still has no TCGdex card data
stays reported as `upstream_missing_card_data` / `unresolved_source_gaps`,
same as before.

## Files

- `supplemental_sets.csv` — per-set overrides (currently just `set_type`)
  and provenance for the set-level classification itself.
- `supplemental_cards.csv` — one row per card, with the same provenance
  columns on every row (a card list can span multiple sources).

## Provenance columns (both files)

- `source_provider` — who/what the data came from (e.g. `bulbapedia`)
- `source_url` — the specific page
- `source_retrieved_at` — ISO date this was looked up
- `verification_source` — a second, independent source used to cross-check
  (never populated from a single unverified source)

## Sets covered so far

- `rc` (Radiant Collection) — the Legendary Treasures (Nov 2013) 25-card
  RC1/RC25–RC25/RC25 subset. Two independent card-database sites
  (Bulbapedia, Pokellector) agree on all 25 names/numbers verbatim.
- `wp` (W Promotional) — all 7 Wizards-era "W stamped" promos. Each card's
  original collector number (from the expansion it's a stamped reprint of)
  cross-checked against multiple independent retailer listings (eBay,
  TCGplayer, SportsCardInvestor, Cardmarket) — not sequential 1–7, per the
  actual printed numbers.
- `sp` (Sample) — mapped specifically to the "Pokémon Center New York,
  August 2002" sample-card group from Bulbapedia's "Sample Set (TCG)"
  article, because that is the one subgroup whose card count (10) and
  non-sequential e-Reader-style numbering (002/093–088/093) match TCGdex's
  own `sp` set metadata (cardCount=10). The same Bulbapedia article also
  documents an E3 2001 group (6 cards, no official numbering — "not known
  to have survived" as physical copies) and an E3 2002 group (2 cards,
  58/165 and 112/165) that are NOT included here, since including them
  would make `sp` an 18-card set, contradicting TCGdex's own count. This
  mapping is inference (count + numbering-style match), not a direct
  TCGdex-to-Bulbapedia confirmation — flagged here rather than silently
  assumed.

## Deliberately NOT covered

- `jumbo` — excluded as `excluded_special_format`, not filled here. See the
  importer's reasoning for that exclusion.
