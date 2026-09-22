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

## Power of Chaos: Yugi the Destiny Limited Collector's Edition — removed, was a duplicate

Originally added as 5 "gap-fill" cards (PCY-001–PCY-005, Prismatic Secret
Rare) based on external research, with a note claiming they were distinct
from the sibling canonical set `Yu-Gi-Oh! Power of Chaos: Yugi the Destiny
promotional cards`. That claim was **wrong** and was caught during
production spot-checks (2026-09-23): all 5 names/codes/rarities are already
present in primary YGOPRODeck data under the `...promotional cards` set
(identical `tcg_date` 2003-11-18), and `cardsets.php` itself lists the
`Limited Collector's Edition` entry with `num_of_cards=2`, not 5 — a
mismatch that had already surfaced as an `expected_unique_vs_numbered_differences`
warning but wasn't individually inspected before production `--apply`.

The 5 rows in `supplemental_cards.csv` are kept with
`status=excluded_special_format` / `exclusion_reason=duplicate_canonical_printing_already_in_primary_data`
for provenance rather than deleted outright. The importer now also tracks
`fullyExcludedSetNames`: when every row for a set_name is excluded (no
`include` rows), the canonical Set row itself is dropped if it ends up with
zero real cards, instead of tripping the `unresolved_source_gaps` FAIL gate
(reported via `dropped_fully_excluded_sets`, never silently). The `Limited
Collector's Edition` should be modeled in the future as a **product/release**
that contains the `...promotional cards` cards, not as a second canonical
card set — the schema doesn't yet have that release/product concept
(canonical card/printing vs. release/product membership are currently
conflated), see the KACB/YUCB audit below for the same underlying gap.

## KACB-EN001 / YUCB-EN001 — same printing, different regional box product (not merged)

Audited 2026-09-23 after the PCY finding raised the same question for
`Kaiba's Collector Box` (KACB-EN001, NA, 2017-11-17) and `Yugi's Collector
Box` (YUCB-EN001, NA, 2017-11-17): both also appear under `Yugi & Kaiba
Collector Box` (2018-03-29). Unlike PCY, this is **not** a data-entry
duplicate — confirmed via Yugipedia/Fandom (authoritative wiki, not
inferred from set name/date alone): `Yugi & Kaiba Collector Box` "is the
European equivalent of the North American Yugi's Collector Box and Kaiba's
Collector Box" (Europe/Oceania/France/Germany/Italy, 2018-03-29), bundling
*both* promo cards (YUCB-EN001 + KACB-EN001) into one box, where NA split
the same two cards across two separate boxes. Same card, same code, same
Ultra Rare rarity in all three listings — a real product/release-membership
difference, not a printing difference (unlike TF05, where NA/EU genuinely
differ in *rarity*). **Left as 2 separate canonical cards per box for now**
— merging would need the same release/product-membership modeling gap
noted above (one canonical printing, multiple release memberships), which
the current schema can't express without either duplicating the canonical
card (current state) or losing the box-membership information entirely.
Do not auto-merge without that schema work.
