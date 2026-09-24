# Binder Catalog Data Provenance Audit

**Date:** 2026-09-25
**Trigger:** while fixing a `card_type` extraction bug in the legacy TCGplayer-based importer (Dragon Ball Super Masters), we found the source site (`dbs-cardgame.com`) explicitly prohibits reproduction of its data. This forced a full stop on new acquisition and a provenance review of every dataset already live in production.

**Status of this document:** initial audit. Every "restricted" / "needs_review" classification below is a flag for a real legal decision (formal publisher licensing, a paid commercial-data provider, or narrowing what we display) — not something this document resolves by itself.

## Hard rule in effect since this audit

No new import, refresh, enrichment, or scrape from:
- `dbs-cardgame.com`
- `bandai-tcg-plus.com` / `api.bandai-tcg-plus.com`
- TCGplayer, or any TCGplayer-derived export (including the existing `cardora-sets-data` folder)

until licensing is resolved. This applies to **all** games currently sourced from `cardora-sets-data`, not just Dragon Ball.

## 1. Full inventory (production, as of this audit)

| game slug | catalog_group | catalog_status (current) | sets | total cards | v2 canonical rows | legacy (TCGplayer) rows |
|---|---|---|---|---|---|---|
| pokemon | tcg | production | 421 | 53,856 | 21,309 | 32,547 |
| yugioh | tcg | production | 1,689 | 85,954 | 38,575 | 47,379 |
| magic-the-gathering | tcg | production | 1,444 | 226,520 | 109,254 | 117,266 |
| one-piece | tcg | production | 143 | 10,059 | 2,799 | 7,260 |
| dragon-ball-super-masters | anime | source_ready → **being set to source_needed** | 106 | 12,019 | 0 | 12,019 |
| dragon-ball-super-fusion-world | anime | source_needed | 1 | 0 | 0 | 0 |
| disney-lorcana | tcg | legacy | 20 | 3,610 | 0 | 3,610 |
| star-wars-miniatures | entertainment | legacy | 22 | 957 | 0 | 957 |
| riftbound | gaming | legacy | 10 | 1,533 | 0 | 1,533 |
| nba/nfl/mlb/ufc/soccer/euroleague/formula-1/fortnite/minecraft/world-of-warcraft/overwatch/naruto/bleach/demon-slayer/jujutsu-kaisen/attack-on-titan/harry-potter/lord-of-the-rings | various | source_needed | sets registered | 0 | 0 | 0 |
| star-wars-unlimited / marvel / dc-comics | various | source_needed | 0 | 0 | 0 | 0 |

"v2 canonical rows" = `binder_cards.card_key IS NOT NULL` (built by the four dedicated importers: `ImportTcgdexPokemon`, `ImportYgoprodeckYugioh`, `ImportScryfallMagic`, `ImportOnePiece`). "Legacy rows" = everything imported by `ImportBinderCatalog`/`ImportDragonBallCatalog` from the `cardora-sets-data` CSV export (TCGplayer-derived, `card_key IS NULL`).

### Frontend / API features that depend on `binder_cards` (all games, no provenance distinction today)

`BinderController::games()` returns **every** `binder_games` row with no filter on `catalog_status`/`catalog_group` — the frontend currently has no concept of "restricted" data. Confirmed consumers, all reading v2 and legacy rows identically:

- `frontend/src/pages/BinderGamesPage.jsx` — public game browser
- `frontend/src/pages/BinderSetCatalogPage.jsx`, `BinderSetDetailPage.jsx` — set/card browsing
- `frontend/src/pages/BinderLibraryPage.jsx`, `BinderDashboardPage.jsx`, `BinderDuplicatesPage.jsx` — user collection tracking (owns cards by `binder_cards.id`)
- `frontend/src/pages/BinderAlertsPage.jsx` — watch-a-set price alerts
- `frontend/src/components/listing/BinderCardMatchField.jsx` — **matches a marketplace listing to a Binder card** at listing-creation time (`CreateListingPage`) — this is the one place legacy data feeds directly into a commercial transaction flow, not just passive browsing
- Admin "Attention Center" widget (`admin-attention-center.blade.php`) surfaces Binder-linked listings in the verification queue

**No destructive action taken.** No rows deleted, no schema dropped.

## 2. The four "canonical v2" sources — do NOT assume GitHub-open-source = licensed for commercial redistribution

### Pokémon — TCGdex (`tcgdex/cards-database`)
| | |
|---|---|
| Code/database repo license | **MIT** (confirmed via GitHub API: `tcgdex/cards-database`) |
| Data license | Same repo, MIT — TCGdex compiles/publishes the database itself under MIT |
| Image terms | Not separately documented; images are served from TCGdex's own CDN, sourced from the compiled database |
| API terms | [tcgdex.dev/faq](https://tcgdex.dev/faq): "The TCGdex API is free to use and requires no API key"; explicitly **encourages local caching** ("for bulk data needs, cache responses locally rather than fetching the same data repeatedly") |
| Attribution | Not mandated in what we found |
| Commercial use | **Not explicitly addressed either way** in TCGdex's own docs |
| Redistribution/cache | **Explicitly encouraged** for bulk use |
| Underlying IP caveat | TCGdex explicitly disclaims any affiliation with The Pokémon Company/Nintendo/Game Freak/Creatures Inc. — the MIT license covers TCGdex's own compiled repo, **not** a grant from The Pokémon Company over the underlying card names/text/images, which remain TPC's IP regardless |

**Classification: `needs_review`.** Best-documented of the four in terms of explicit reuse/caching permission, but commercial use isn't explicitly addressed, and the underlying game IP owner (TPC) has not itself licensed this.

### Yu-Gi-Oh! — YGOPRODeck
| | |
|---|---|
| Code license | N/A (this is a hosted API, not a data repo we vendor) |
| Data/API terms | [ygoprodeck.com/api-guide](https://ygoprodeck.com/api-guide/): "**You are free to use the data however you wish**"; explicitly **requires** downloading/storing data and images locally rather than hotlinking |
| Image terms | Must download and re-host locally; no hotlinking (enforced via IP blacklist) |
| Attribution | No formal mandate found |
| Commercial use | Explicit "free to use however you wish" — most permissive language of the four |
| Redistribution/cache | **Explicitly required**, not just permitted |
| Underlying IP caveat | Explicit disclaimer: "not produced by, endorsed by, supported by, or affiliated with 4k Media or Konami Digital Entertainment." Card text/images remain Konami's copyright; YGOPRODeck operates on what it calls "fair-use community-database conventions" — a legal theory, not a grant from Konami |

**Classification: `needs_review`.** Most explicit "use it however you wish" language of the four, but same underlying-rightsholder caveat as Pokémon/TCGdex — Konami itself has not licensed this.

### Magic: The Gathering — Scryfall
| | |
|---|---|
| Data/API terms | [scryfall.com/docs/api](https://scryfall.com/docs/api), "Use of Scryfall Data and Images" section — operates explicitly **"as part of the Wizards of the Coast Fan Content Policy"** |
| Commercial use | Fan Content Policy requires Fan Content to **remain free**: "your Fan Content must be free for others... to view, access, share, and use without paying you anything" and "you can't sell or license your Fan Content to any third parties for any type of compensation" |
| Redistribution/cache | Allowed if you "create additional value for end-users" — "you may not simply repackage, republish, or proxy Scryfall data" |
| Image terms | Detailed, explicit rules (don't crop/distort/watermark, keep copyright/artist name visible, credit artist when using `art_crop`) |
| Attribution | Must not imply Scryfall endorsement; Fan Content must be marked unofficial |
| Underlying IP caveat | This is the **only one of the four operating under an actual publisher policy** (Wizards of the Coast), not just a fan project's own unilateral terms |

**Classification: `needs_review` — highest confidence of the four, but with a real open question.** Scryfall's own terms are the clearest and are publisher-sanctioned, but the Fan Content Policy's "must remain free" / "can't sell... for compensation" language is written for hobby fan projects, not a for-profit marketplace charging transaction fees. Cardora doesn't charge to view card data, but it *is* a commercial business built partly around this catalog — whether that still fits "Fan Content" is a genuine legal question, not one this audit can resolve. **Recommend actual legal review of the Fan Content Policy against Cardora's business model specifically**, since this is the closest thing to a real path to compliance we've found for any of the four games.

### One Piece — punk-records/vegapull (fork of `coko7/vegapull`)
| | |
|---|---|
| Code license | **GPL-3.0-or-later** — but this licenses the **scraper tool's code only** |
| Data source | The tool scrapes `onepiece-cardgame.com` directly |
| Data license | **None.** The tool's own documentation states data obtained is "copyrighted by ©Eiichiro Oda/Shueisha, Toei Animation, Bandai Namco Entertainment Inc." |
| Site terms | Checked `onepiece-cardgame.com` directly — footer states (Japanese): *"このwebサイトに記載されているすべての画像・テキスト・データの無断転用、転載をお断りします"* — "All images, text, and data on this website are prohibited from unauthorized reproduction or reposting." **Same publisher (Bandai), same prohibition, as `dbs-cardgame.com`.** |
| Additionally | The One Piece DON!! card research (already in production) was compiled by directly reading official Bandai pages/PDFs — same source family |

**Classification: `restricted`.** This is exactly the trap flagged before starting this audit: an open-source (GPL) *tool* was mistaken for an open *data* license. The One Piece v2 canonical catalog (2,799 cards, live in production) has **the same provenance problem we just found for Dragon Ball**, not a cleaner one. This needs the same treatment as Dragon Ball: no further acquisition/enrichment from this source, and a real decision about the existing 2,799 rows already live.

## 3. TCGplayer / `cardora-sets-data` (the legacy dataset underlying 8+ games)

Checked the [TCGplayer API Terms & Conditions](https://help.tcgplayer.com/hc/en-us/articles/360061115874-TCGplayer-API-Terms-Conditions) directly (updated June 8, 2022). Relevant prohibited activities, quoted verbatim:

> "Develop, promote, or enable any product, application, or service similar to or that competes with TCGplayer's current or planned offerings, or the Site itself."
> "Distribute TCG Content or otherwise make it available to your end users or third parties, including any other developers, for commercial or competitive purposes."
> "Collect content or information (including pricing information) from the Site using automated means... other than through API access as provided by these API Terms."
> "Obtain content or information (including pricing information) from a third-party that was collected from the Site using our API or otherwise using automated means, as described above."
> "Access or collect content or information using TCG Content in order to build, enhance, improve or promote a similar or competitive website, product, or service."

Cardora is a competing card marketplace. Regardless of whether `cardora-sets-data` was obtained via a sanctioned API grant or downloaded as a third-party export, its use here appears to conflict directly with TCGplayer's stated terms.

**Classification: `restricted_or_unverified`**, applied to every row where `binder_cards.card_key IS NULL` (the legacy import path), across all affected games: `pokemon` (32,547 rows), `yugioh` (47,379), `magic-the-gathering` (117,266), `one-piece` (7,260), `dragon-ball-super-masters` (12,019), `disney-lorcana` (3,610), `star-wars-miniatures` (957), `riftbound` (1,533).

This status is **recorded here as documentation only** — no DB column was added and no rows were touched, pending the backup/dependency-mapping/replacement-plan sequencing requested before any schema or data change.

## 4. Dragon Ball Super — frozen

`dragon-ball-super-masters.catalog_status` is being set from `source_ready` to **`source_needed`**, and `dragon-ball-super-fusion-world` remains `source_needed`. The 12,019 already-imported legacy rows are **not deleted** (that would be destructive and wasn't requested) — they stay in place, unused as a canonical source, pending one of:

- a direct Bandai/publisher license,
- a licensed commercial trading-card data provider,
- a community dataset with an explicit data license permitting commercial reuse (none found as of this audit — `apitcg.com` doesn't cover Masters at all; the two GitHub repos found, `B0rjitaaa/DragonBall-EnhancedAPI` and `dragogodev/cgs`, both have **no LICENSE file** per the GitHub API, and the former is itself just a `dbs-cardgame.com` scraper),
- a direct partnership with Bandai.

The legacy TCGplayer export is explicitly **not** an acceptable fallback per this decision.

## 5. Open items / recommended next steps (not actioned in this pass)

1. **Full backup** — done, `pre-provenance-audit-20260924-222713.sql.gz` (44MB), verified and stored both on production (`backend/storage/app/db-backups/`) and locally.
2. **Dependency mapping** — done above (section 1). Every Binder frontend feature currently treats all provenance tiers identically; there is no existing mechanism to hide/flag restricted-provenance data from end users.
3. **Replacement plan per game** — not yet drafted. Given the scale (8 games, ~211K legacy rows), this needs its own dedicated pass once there's a decision on whether any of the "needs_review" v2 sources (TCGdex/YGOPRODeck/Scryfall) require formal action too, since Scryfall in particular may need actual legal review rather than a data-source swap.
4. **One Piece** — flagged here as newly `restricted`, same as Dragon Ball. Not actioned (no status change made to `one-piece` in this pass); awaiting direction, since unlike Dragon Ball it's already marked `catalog_status = production` and actively surfaced as a flagship canonical game.
