<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Piece TCG's source data (punk-records/vegapull) disagrees with
     * itself on `effect` wording for 26 base card numbers across
     * different print runs -- genuine errata between printings (confirmed
     * by direct inspection, e.g. "your Life area" corrected to "the top
     * of your Life cards" in a reprint), not scraping noise. Rather than
     * either (a) splitting these into separate canonical cards just
     * because of wording drift, or (b) silently losing what's actually
     * printed on the non-canonical printing's physical card, the
     * canonical `effect` field stays a single shared value on
     * binder_cards (the card's home printing's text), and this column
     * captures a variant's own printed wording ONLY when it differs from
     * that canonical text -- nullable, and left null for the overwhelming
     * majority of variants where the two already match.
     */
    public function up(): void
    {
        Schema::table('binder_card_variants', function (Blueprint $table) {
            $table->text('printed_effect_text')->nullable()->after('rarity');
        });
    }

    public function down(): void
    {
        Schema::table('binder_card_variants', function (Blueprint $table) {
            $table->dropColumn('printed_effect_text');
        });
    }
};
