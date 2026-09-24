<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two independent concepts, deliberately not conflated (a game whose
     * `category` is a rules-based card game can belong to a UI grouping
     * that isn't "tcg" -- e.g. Dragon Ball Super Card Game: Fusion World
     * is category=tcg but displays under the "anime" tab, not the main
     * TCG tab):
     *
     * catalog_group: which tab/section of the game picker this shows
     * under (tcg/sports/gaming/anime/entertainment). Purely a UI
     * grouping concern.
     *
     * catalog_status: whether this game has a real, importable canonical
     * card dataset yet -- production (the 4 catalog-v2 games, now also
     * gameplay_data-enriched), legacy (has old flat-schema rows from
     * before catalog v2, never rebuilt), source_ready (a real
     * card-level dataset exists and could be imported), source_needed
     * (the game/product line is known and registered, but no reliable
     * card-level source has been found/verified yet -- binder_sets may
     * exist for known real product names, binder_cards must stay empty
     * until real sourcing happens, never backfilled from an
     * "ERROR, No URL found" placeholder).
     *
     * `category` (tcg/sports/gaming, plus the new 'collectible' value
     * for non-rules-based collectibles like comic-book singles) is left
     * untouched -- it was never a DB-level enum (plain string column),
     * so adding a new value needs no schema change, only an application
     * convention documented here and in GameCatalogRegistry.
     */
    public function up(): void
    {
        Schema::table('binder_games', function (Blueprint $table) {
            $table->string('catalog_group')->nullable()->after('category');
            $table->string('catalog_status')->default('source_needed')->after('catalog_group');

            $table->index(['catalog_group']);
            $table->index(['catalog_status']);
        });
    }

    public function down(): void
    {
        Schema::table('binder_games', function (Blueprint $table) {
            $table->dropIndex(['catalog_group']);
            $table->dropIndex(['catalog_status']);
            $table->dropColumn(['catalog_group', 'catalog_status']);
        });
    }
};
