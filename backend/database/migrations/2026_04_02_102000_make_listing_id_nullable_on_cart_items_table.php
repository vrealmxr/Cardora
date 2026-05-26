<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function ($table) {
            $table->dropForeign(['listing_id']);
        });

        DB::statement('ALTER TABLE cart_items MODIFY listing_id BIGINT UNSIGNED NULL');

        Schema::table('cart_items', function ($table) {
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('cart_items')->whereNull('listing_id')->delete();

        Schema::table('cart_items', function ($table) {
            $table->dropForeign(['listing_id']);
        });

        DB::statement('ALTER TABLE cart_items MODIFY listing_id BIGINT UNSIGNED NOT NULL');

        Schema::table('cart_items', function ($table) {
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
        });
    }
};
