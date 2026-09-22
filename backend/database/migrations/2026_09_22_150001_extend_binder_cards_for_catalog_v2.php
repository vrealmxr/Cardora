<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * binder_cards becomes the CANONICAL card (one row per real-world card,
     * e.g. "Charizard #004" — not one row per printing/variant). The old
     * columns (number, rarity, image_url, external_product_id) stay for now
     * so nothing already reading this table breaks; rarity/image/source-id
     * belong on binder_card_variants going forward and get backfilled there
     * in the data-migration pass, not deleted here.
     */
    public function up(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->string('card_key')->nullable()->after('external_product_id');
            $table->boolean('is_promo')->default(false)->after('card_type');
            $table->boolean('is_token')->default(false)->after('is_promo');
            $table->string('language', 8)->default('EN')->after('is_token');
        });

        Schema::table('binder_cards', function (Blueprint $table) {
            $table->unique('card_key');
        });
    }

    public function down(): void
    {
        Schema::table('binder_cards', function (Blueprint $table) {
            $table->dropUnique(['card_key']);
            $table->dropColumn(['card_key', 'is_promo', 'is_token', 'language']);
        });
    }
};
