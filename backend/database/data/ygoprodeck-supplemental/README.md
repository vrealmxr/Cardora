# YGOPRODeck supplemental dataset

Two files, both hand-researched and cross-checked one record at a time,
never used automatically beyond the exact `set_name`/`collector_number`/
`rarity` values listed:

- **`supplemental_cards.csv`** — fills gaps the primary YGOPRODeck import
  has no card-level data for at all (`upstream_missing_card_data`). Mirrors
  `database/data/tcgdex-supplemental/` for the Pokémon importer. Every row
  carries provenance (`source_provider`/`source_url`/`source_retrieved_at`/
  `verification_source`) plus a `status` column: `include` (normal card) or
  `excluded_special_format` (a real physical card, recorded for the
  roadmap, but not imported into the standard catalog — e.g. oversized
  promos).
- **`supplemental_variant_overrides.csv`** — tags `region_code`/
  `edition_code` (see `database/migrations/2026_09_23_010000_add_region_and_edition_codes_to_binder_card_variants.php`)
  onto variants that **already exist** (from primary or supplemental card
  data) — it never creates a card, only annotates a printing dimension
  rarity alone can't express. Matched by `(set_name, collector_number,
  rarity)`.

A set/card not listed in `supplemental_cards.csv` that still has no
YGOPRODeck card data stays reported as `upstream_missing_card_data`.

## Sets covered

- **Adidas Collaboration Card** — ADC1-EN001 Dark Magician, Secret Rare.
- **Kaiba's Collector Box** — KACB-EN001 Blue-Eyes White Dragon, Ultra Rare
  (standard size, included). The oversized printing of the same card is
  recorded with `status=excluded_special_format` — confirmed to exist as a
  genuinely separate physical product (Collector's Cache LLC lists it as its
  own catalog entry) — not included in the standard catalog. The 50-card
  Starter Deck and booster packs bundled in this box are **not** modeled
  here; they belong to their own existing sets.
- **Yugi's Collector Box** — same pattern: YUCB-EN001 Dark Magician, Ultra
  Rare standard size included; oversized printing excluded_special_format.
- **The Lost Art Promotion 2023 D** — LART-EN057 Senju of the Thousand
  Hands, Ultra Rare.
- **Yu-Gi-Oh! Power of Chaos: Yugi the Destiny Limited Collector's Edition**
  — all 5 cards (PCY-001–PCY-005). Rarity is **Prismatic Secret Rare**
  (PScR) per Yugipedia, not plain "Secret Rare" — verify this if a
  different source is consulted later, since retailer listings for the
  broader "Power of Chaos" promo line sometimes use the shorter name.

## Yu-Gi-Oh! 5D's Tag Force 5 Promotional Cards (TF05) — resolved

Originally deferred pending a schema question (`region_code`/`edition_code`
added to `binder_card_variants`, see the migration above), then found to
need **no new card data at all**: TF05-EN001 (Fleur Synchron), TF05-EN002
(Chevalier de Fleur) and TF05-EN003 (Liberty at Last!) were already present
in YGOPRODeck's primary `card_sets` data with **both** rarities (Ultra Rare
and Super Rare). What blocked them from resolving was a casing
inconsistency in YGOPRODeck's own data — some `card_sets` entries use
`"...promotional cards"` (lowercase) while `cardsets.php` registers the
canonical set as `"...Promotional Cards"` (capital P, C) — fixed generally
in the importer via case-insensitive set_name matching (falls back to the
canonical casing, not just this one set).

`supplemental_variant_overrides.csv` then only tags which rarity is which
region — Ultra Rare=`na`, Super Rare=`eu`, confirmed per-card via Yugipedia
set card lists (TCG-NA / TCG-EU) and cross-checked against Cardmarket
("Fleur Synchronique (V.2 - Super Rare)") and multiple NA retailers. Both
regions use the identical printed code (no `-FR`/other suffix) for the
English releases specifically.
