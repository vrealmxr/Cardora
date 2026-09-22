# YGOPRODeck supplemental dataset

Fills gaps the primary YGOPRODeck import doesn't have card-level data for
(`upstream_missing_card_data`) — mirrors `database/data/tcgdex-supplemental/`
for the Pokémon importer. Every row carries provenance
(`source_provider`/`source_url`/`source_retrieved_at`/`verification_source`)
plus a `status` column: `include` (normal card) or `excluded_special_format`
(a real physical card, recorded for the roadmap, but not imported into the
standard catalog — e.g. oversized promos).

Never used automatically — only for the exact `set_name` values listed here,
researched and cross-checked one set at a time. A set not listed here that
still has no YGOPRODeck card data stays reported as `upstream_missing_card_data`.

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

## Deliberately NOT covered yet — blocked on a schema question

**Yu-Gi-Oh! 5D's Tag Force 5 promotional cards** (TF05-EN001 Fleur Synchron,
TF05-EN002 Chevalier de Fleur, TF05-EN003 Liberty at Last!) has a confirmed
**region-dependent rarity**: North America English = Ultra Rare, Europe
English = Super Rare, for all three cards. `binder_card_variants` (see
`database/migrations/2026_09_22_150002_create_binder_card_variants_table.php`)
has no `region` column — only `card_id, variant_key, variant_name,
variant_type, rarity, artist, image_small, image_large, sort_order` — and
`binder_cards` has `language` but not `region` either. Expressing "same
rarity name, different region" by encoding region into `variant_name` (e.g.
"Ultra Rare (NA)") would work mechanically but wasn't done here without
sign-off, since it's a schema-modeling decision, not card data. Left out of
this import entirely (not merged into one rarity, not guessed) pending that
decision.
