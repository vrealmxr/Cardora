<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two real printing dimensions that "rarity" alone can't express: the
     * same rarity NAME can mean two different physical prints depending on
     * region (Yu-Gi-Oh! Tag Force 5 promos: Ultra Rare in North America,
     * Super Rare in Europe, for the identical card_key), and edition
     * (1st Edition vs Unlimited, already handled ad hoc via variant_type
     * suffixes for Pokémon — this gives it a real column instead).
     *
     * Deliberately free strings, not enums — the set of real-world
     * regions/editions a printing can carry isn't closed, and locking it
     * down now would just mean another migration the first time a new one
     * shows up. `language` (on binder_cards) and `region` are different
     * concepts and stay separate: "EN" in a set/card code or
     * language=en does NOT mean North American and European English
     * printings are the same physical card.
     */
    public function up(): void
    {
        Schema::table('binder_card_variants', function (Blueprint $table) {
            $table->string('region_code')->nullable()->after('rarity'); // e.g. 'na' | 'eu' | 'jp' | 'kr' | 'asia' | null (global/unknown/not applicable)
            $table->string('edition_code')->nullable()->after('region_code'); // e.g. 'first_edition' | 'unlimited' | 'limited' | null

            $table->index(['region_code']);
            $table->index(['edition_code']);
        });
    }

    public function down(): void
    {
        Schema::table('binder_card_variants', function (Blueprint $table) {
            $table->dropIndex(['region_code']);
            $table->dropIndex(['edition_code']);
            $table->dropColumn(['region_code', 'edition_code']);
        });
    }
};
