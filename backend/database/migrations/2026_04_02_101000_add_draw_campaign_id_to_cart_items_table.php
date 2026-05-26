<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('draw_campaign_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->unique(['user_id', 'draw_campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_user_id_draw_campaign_id_unique');
            $table->dropConstrainedForeignId('draw_campaign_id');
        });
    }
};
