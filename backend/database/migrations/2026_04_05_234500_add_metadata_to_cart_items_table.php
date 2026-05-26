<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_user_id_listing_id_unique');
            $table->longText('metadata')->nullable()->after('quantity');
            $table->index(['user_id', 'listing_id'], 'cart_items_user_listing_index');
            $table->index(['user_id', 'draw_campaign_id'], 'cart_items_user_draw_index');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_user_listing_index');
            $table->dropIndex('cart_items_user_draw_index');
            $table->dropColumn('metadata');
            $table->unique(['user_id', 'listing_id']);
        });
    }
};
