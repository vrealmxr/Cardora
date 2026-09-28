# Cardora Binder Catalog — Export Guide for Image-Recognition Training

This document explains the data behind Cardora's "Binder" feature — a
catalog of trading cards and sports cards — and how to use the exported
manifest (`binder-catalog-manifest.csv`) to train or build a system that
identifies a physical card from a photo.

## 1. What Binder actually is

Binder is a digital catalog + collection tracker across 11 card
franchises, organized in a 4-level hierarchy:

```
Game (e.g. "Magic: The Gathering")
  └── Set (e.g. "Bloomburrow" — one physical product release)
        └── Card (e.g. "Sable Wraith" — one canonical card design)
              └── Variant (e.g. the plain printing, vs. the foil,
                            vs. a borderless alternate-art version)
```

**Why the Card/Variant split matters for a recognition bot**: two
variants of the same card can be visually very different (foil sheen,
different border art, different frame treatment) while sharing the same
name/rules text. If you're training a classifier, **the variant is your
real visual label**, not the card. Two photos of the same *card* but
different *variants* should NOT be treated as the same training class —
they can look nothing alike.

## 2. The 11 games and what data exists for each

| Game | Category | Sets | Cards | Variants | Variants with an image |
|---|---|--:|--:|--:|--:|
| Magic: The Gathering | TCG | 991 | 109,254 | 161,453 | 161,291 |
| Pokémon | TCG | 204 | 21,309 | 30,839 | 29,128 |
| Yu-Gi-Oh! | TCG | 1,033 | 38,575 | 44,539 | 44,499 |
| One Piece Card Game | TCG | 59 | 2,799 | 4,859 | 4,843 |
| Disney Lorcana | TCG | 25 | 3,265 | 5,905 | 5,905 |
| Riftbound | TCG | 8 | 1,316 | 1,451 | 1,451 |
| Star Wars: Unlimited | TCG | 50 | 9,995 | 11,503 | 11,503 |
| Dragon Ball Super CG: Fusion World | TCG | 28 | 2,027 | 4,120 | 4,120 |
| NBA | Sports | 188 | 148,443 | 1,349,300 | **0** |
| Soccer | Sports | 19 | 5,454 | 45,971 | **0** |
| Euroleague | Sports | 7 | 4,451 | 40,693 | **0** |

**The 3 sports catalogs have zero images.** They come from manually
curated checklist spreadsheets (Panini/Topps product checklists), which
list card names/numbers/teams but were never photographed or scanned.
**A photo-recognition bot cannot be trained on NBA/Soccer/Euroleague at
all with the current data** — there is nothing to compare a photo against.
If you need this later, it's a separate, much larger sourcing project
(no public API/source has these images either — already investigated).

The 8 TCGs all have near-complete image coverage (95–100%). The handful
of missing ones (e.g. some very recent/niche Pokémon promo sets) are
gaps in the *original source itself* (confirmed directly against
TCGdex's API), not something missing on our end.

## 3. Where the images actually live

Two different situations, both already resolved to a plain HTTP(S) URL
in the manifest's `image_url` column — you don't need to know which is
which to use the export, but it matters if something ever needs
re-fetching or re-hosting:

- **Magic, Pokémon, Yu-Gi-Oh!**: hotlinked directly to the original
  public card databases (Scryfall, TCGdex, YGOPRODeck). These all serve
  images with open CORS (`Access-Control-Allow-Origin: *`) — safe and
  fast to bulk-download, they're built for exactly this kind of
  programmatic access.
- **One Piece, Disney Lorcana, Riftbound, Star Wars: Unlimited, Dragon
  Ball Fusion World**: downloaded once and cached on Cardora's own
  server (`https://api.cardora.gr/cards/...`). One Piece specifically
  had to be mirrored this way because the official source
  (onepiece-cardgame.com) sends a `Cross-Origin-Resource-Policy:
  same-site` header that blocks any other site from displaying its
  images at all — so those files are re-hosted on our own infrastructure.

## 4. The manifest file (`binder-catalog-manifest.csv`)

One row per **variant** (the real visual unit, see §1). Columns:

| Column | Meaning |
|---|---|
| `game_slug` / `game_name` / `game_category` | Which franchise (`category` is `tcg` or `sports`) |
| `set_key` / `set_name` / `set_code` / `set_released_at` | Which product release |
| `card_key` | Canonical card identity — stable across all its variants |
| `card_name` / `card_number` / `rarity` / `card_type` | Card-level identity fields |
| `team` | Sports only — the player's team (from the checklist data) |
| `variant_key` | **The real training label.** Unique per visually-distinct printing. |
| `variant_name` / `variant_type` | Human-readable description of the parallel/finish (e.g. "Foil", "Prizm Blue Wave /150") |
| `artist` | Card illustrator, where known (mainly Lorcana/Riftbound/SW:Unlimited) |
| `image_url` | Direct, working HTTPS URL to that variant's image. This is what you download/feed to training. |
| `has_image` | `1`/`0` — filter on this before trying to fetch |

Row count: run `wc -l binder-catalog-manifest.csv` — expect roughly
**1.65 million** (variant count across the 8 TCGs + 3 sports catalogs
combined, per the table above), of which the ~1.44M sports rows will
have `has_image=0`.

## 5. Practical steps to actually build the recognition system

1. **Filter to `has_image=1`** — this drops all 3 sports catalogs and a
   small number of TCG gaps, leaving ~284,000 usable rows across the 8
   TCGs.
2. **Download images by URL.** Since ~161K of the busiest rows
   (Magic) point at Scryfall, check whether their **bulk data** export
   (`https://scryfall.com/docs/api/bulk-data`) is faster than 161K
   individual HTTP requests — it almost certainly is. TCGdex and
   YGOPRODeck don't offer bulk archives, so those are fetched one URL
   at a time (both tolerate reasonable concurrent request volume; this
   project fetches them at ~10–15 concurrent requests without issue).
3. **Group by `variant_key`, not `card_key`**, when building your
   training set / label list, per §1.
4. **Recommended approach given the data you actually have**: a single
   reference photo per variant (official card art, not real-world
   photos of a physical card in someone's hand) is well suited to an
   **embedding/similarity-search** approach (e.g. CLIP-style image
   embeddings + nearest-neighbor lookup) rather than training a
   classifier from scratch — you don't have multiple real-world photos
   per card to train a traditional classifier on, only one canonical
   image per variant. This is also what Cardora's own in-app "Cardora
   Scanner" page is meant to eventually do (it currently only does a
   text-name search — no image recognition is wired up yet on the
   product side).
5. If the target model needs the raw files rather than URLs, the
   locally-hosted ones (One Piece, Lorcana, Riftbound, SW:Unlimited,
   Fusion World) total **~6.3GB** deduplicated and can be pulled
   directly from `api.cardora.gr/cards/...` or copied off the server's
   `backend/public/cards/` directory.

## 6. Known gaps (already investigated, not re-open items)

- No card images exist for NBA/Soccer/Euroleague (§2).
- No *set-level* cover art (box art / set logo) exists for Riftbound,
  One Piece, Disney Lorcana, Star Wars: Unlimited, or Dragon Ball Fusion
  World — checked both their data APIs and their official marketing
  sites directly. Doesn't affect card-level recognition training at all,
  only cosmetic set-browsing UI.
- A small number of Yu-Gi-Oh! `binder_sets` rows are stale duplicates
  from an older pre-canonical import; harmless for this export (the
  manifest already excludes non-canonical rows via `card_key IS NOT
  NULL`) but flagged separately for cleanup.
