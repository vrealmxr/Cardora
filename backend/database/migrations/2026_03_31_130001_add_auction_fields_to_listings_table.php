<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('sale_format')->default('fixed_price')->after('status');
            $table->longText('auction_settings')->nullable()->after('is_featured');
            $table->decimal('starting_bid', 10, 2)->nullable()->after('auction_settings');
            $table->decimal('current_bid', 10, 2)->nullable()->after('starting_bid');
            $table->decimal('reserve_price', 10, 2)->nullable()->after('current_bid');
            $table->decimal('bid_increment', 10, 2)->nullable()->after('reserve_price');
            $table->decimal('buyout_price', 10, 2)->nullable()->after('bid_increment');
            $table->timestamp('auction_starts_at')->nullable()->after('buyout_price');
            $table->timestamp('auction_ends_at')->nullable()->after('auction_starts_at');
            $table->foreignId('winning_bidder_id')->nullable()->after('auction_ends_at')->constrained('users')->nullOnDelete();
            $table->longText('lot_snapshot')->nullable()->after('winning_bidder_id');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winning_bidder_id');
            $table->dropColumn([
                'sale_format',
                'auction_settings',
                'starting_bid',
                'current_bid',
                'reserve_price',
                'bid_increment',
                'buyout_price',
                'auction_starts_at',
                'auction_ends_at',
                'lot_snapshot',
            ]);
        });
    }
};

