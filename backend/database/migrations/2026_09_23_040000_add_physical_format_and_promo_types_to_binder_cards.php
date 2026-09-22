<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two independent, print-level signals that don't fit anywhere else
     * yet -- deliberately plain columns, not new tables, matching
     * region_code/edition_code/oracle_id:
     *
     * physical_format_code: the physical form factor of the card itself
     * (currently only "oversized" for Scryfall's `oversized` flag, null
     * for standard/unspecified) -- lets Pokémon Jumbo cards be folded into
     * the same column later without another schema change.
     *
     * promo_types: Scryfall's `promo_types` array, stored verbatim as JSON
     * rather than converted into anything (never auto-mapped to
     * binder_releases -- that list mixes distribution context, foil
     * treatment, and printing attributes, which need curated human review
     * to separate, not an automatic rule). Preserves collectible
     * characteristics (surgefoil, confettifoil, serialized, textured...)
     * that the coarser `finish` column ("foil") alone would lose.
     */
    public function up(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->string('physical_format_code')->nullable()->after('oracle_id');
            $table->json('promo_types')->nullable()->after('physical_format_code');

            $table->index(['physical_format_code']);
        });
    }

    public function down(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->dropIndex(['physical_format_code']);
            $table->dropColumn(['physical_format_code', 'promo_types']);
        });
    }
};
