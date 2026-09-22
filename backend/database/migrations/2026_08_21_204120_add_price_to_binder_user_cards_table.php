<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binder_user_cards', function (Blueprint $table) {
            // What the user says the card is worth — self-reported, not a
            // live market price. Nullable: a card can be checked off before
            // a price is set (defensive fallback in the API, not the normal
            // flow — the UI always prompts for a price on check).
            $table->decimal('price', 10, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('binder_user_cards', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
