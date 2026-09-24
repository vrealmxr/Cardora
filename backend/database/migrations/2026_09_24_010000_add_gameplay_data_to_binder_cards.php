<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resolved canonical gameplay metadata (color/cost/power/atk-def/hp-
     * attacks/mana_cost-type_line/... depending on the game), one JSON
     * envelope per canonical card, applicable to all four games via a
     * shared envelope + game-native `data` vocabulary -- not a shared
     * flat key set, since e.g. Magic's power/toughness pair, Yu-Gi-Oh's
     * atk/def, Pokémon's per-attack damage, and One Piece's power/counter
     * are genuinely different concepts, not synonyms. See the design
     * discussion for the four per-game `data` shapes.
     *
     * Envelope: {schema_version, source, source_version, resolved_at,
     * provenance: [], data: {}}. `source` is the primary upstream feed
     * (tcgdex/ygoprodeck/scryfall/onepiece-cardgame) and is never replaced
     * wholesale just because `provenance` recorded a supplemental
     * override on 1-2 fields -- mirrors the existing One Piece
     * supplemental_gameplay_overrides.csv pattern (see
     * ImportOnePiece::applyGameplayOverrides()), generalized to all four
     * importers instead of being One Piece-only.
     *
     * Distinct from binder_card_variants.printed_effect_text: that column
     * is the exact wording on one specific printing; `data.effect`/
     * `data.oracle_text`/`data.desc` here is the resolved canonical text
     * on the card itself.
     *
     * Deliberately not backfilled by this migration -- additive schema
     * change only, population happens via a separate importer change +
     * backfill command once the JSON shape is confirmed against real
     * source samples for all four games.
     */
    public function up(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->json('gameplay_data')->nullable()->after('promo_types');
        });
    }

    public function down(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->dropColumn('gameplay_data');
        });
    }
};
