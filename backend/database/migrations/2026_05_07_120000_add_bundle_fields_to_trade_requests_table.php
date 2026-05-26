<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_requests', function (Blueprint $table): void {
            $table->longText('offered_listing_ids')->nullable()->after('offered_images');
            $table->longText('target_listing_ids')->nullable()->after('offered_listing_ids');
            $table->longText('swap_pairs')->nullable()->after('target_listing_ids');
        });
    }

    public function down(): void
    {
        Schema::table('trade_requests', function (Blueprint $table): void {
            $table->dropColumn(['offered_listing_ids', 'target_listing_ids', 'swap_pairs']);
        });
    }
};
