<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real, first-party price history — populated from Cardora's own
        // marketplace activity for cards matched to the Binder catalog (a
        // listing going live, and a sale actually completing). No external
        // market-data API involved.
        Schema::create('binder_card_price_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('binder_card_id')->constrained('binder_cards')->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('source'); // listing_created | listing_sold
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['binder_card_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binder_card_price_points');
    }
};
