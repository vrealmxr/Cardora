# Gameplay snapshots (binder_cards.gameplay_data enrichment)

Two independent layers:

- **canonical catalog v1** — immutable identity/sets/cards/variants, already `--apply`'d to
  production (Pokémon/Yu-Gi-Oh!/Magic/One Piece). See `../tcgdex-supplemental/`,
  `../ygoprodeck-supplemental/`, `../onepiece-supplemental/` for its own supplemental data.
- **gameplay snapshot v1** (this directory) — independently frozen raw source data used
  *only* to populate `binder_cards.gameplay_data`. The gameplay backfill (`cardora:gameplay-backfill`)
  never creates/deletes/merges cards or variants, and never mutates canonical identity — it only
  `UPDATE`s an existing row's `gameplay_data` column.

## What's here vs. what's not

Each `<game>/manifest.json` is git-tracked and small (a few KB). The raw snapshot files
themselves (21MB–79MB each) are **not** committed to git — they live at
`storage/app/gameplay-snapshots/<game>/` on the production server (frozen storage, outside the
normal deploy rsync path — see `scripts/deploy.sh`'s `--exclude 'storage/'`). Each manifest
records enough to re-acquire or re-verify that exact raw content:

| Game | Pin | Re-verifiable via |
|---|---|---|
| Magic | exact Scryfall bulk-data file, by URL | SHA256 (byte-for-byte verified against the canonical import's own manifest) |
| Pokémon | `tcgdex/cards-database` git commit | clone + `bun run compile`, then SHA256 of the output |
| Yu-Gi-Oh! | none (no upstream versioning exists) | not re-verifiable byte-for-byte — see the manifest's note |
| One Piece | `buhbbl/punk-records` git commit | clone/checkout + tarball SHA256 |

## Provenance discipline

`canonical_catalog_commit` in each manifest is the best-effort commit associated with that
game's canonical v1 import — for Magic and Yu-Gi-Oh! it's read directly from their own
production `manifest.json`/`validation_report.json`; for Pokémon and One Piece no such artifact
survives anywhere reachable (production, this repo, or a local checkout), so it's inferred from
git log and flagged as such, never presented as a recorded fact.

None of the four `canonical_catalog_tag` fields exist yet (`magic-catalog-v1`,
`yugioh-catalog-v1`, `onepiece-catalog-v1` are all still pending — a separate task from
gameplay_data enrichment).

## Reproducing a run

`php artisan cardora:gameplay-backfill --game=<game>|all --dry-run` reads
`storage/app/gameplay-snapshots/<game>/manifest.json` + the raw file(s) it names, resolves
against the current `binder_cards`/`binder_card_external_ids`/`binder_card_variants` tables
read-only, and prints the coverage/conflict report — no writes. `--apply` requires every
requested game's dry-run to be clean first (see the command's hard-FAIL gate) and then only ever
issues `UPDATE binder_cards SET gameplay_data = ... WHERE card_key = ...`.

See `validation_report.md` in this directory for the last dry-run's full per-game numbers and
the coverage reconciliation (every unmatched/missing record traced to a specific documented
cause, not left as an unexplained gap).
