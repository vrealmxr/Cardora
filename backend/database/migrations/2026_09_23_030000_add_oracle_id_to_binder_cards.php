<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Magic: The Gathering's "Oracle card / card design" identity
     * (Scryfall's `oracle_id`) — shared across every printing/reprint of
     * the same card, distinct from the printing itself (binder_cards.id)
     * and its card_key (which is scryfall_id-based, not set+number-based,
     * see ImportScryfallMagic). Deliberately a plain nullable column, not
     * a separate table: Scryfall already duplicates oracle-level text onto
     * every printing, so there's no data-integrity win to normalizing it
     * out today — GROUP BY oracle_id already reconstructs "every printing
     * of this card" without one. Promotable to a real table later
     * (additive migration) if a concrete need for oracle-level shared
     * fields shows up.
     */
    public function up(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->string('oracle_id')->nullable()->after('card_key');
            $table->index(['oracle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->dropIndex(['oracle_id']);
            $table->dropColumn('oracle_id');
        });
    }
};
