<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The actual purchasable/trackable printing of a canonical card — e.g.
     * Charizard #004 exists as "Normal", "1st Edition Holo", "Shadowless
     * Holo" variants, each with its own rarity, image and marketplace
     * identity. This is what binder_user_cards, binder_card_price_points
     * and products.binder_card_id should point at going forward (a
     * separate, deliberate migration — not part of this pass).
     */
    public function up(): void
    {
        Schema::create('binder_card_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('binder_cards')->cascadeOnDelete();
            $table->string('variant_key')->unique();
            // e.g. "Holo", "Reverse Holo", "1st Edition", "Master Ball Reverse"
            $table->string('variant_name');
            // Coarser grouping for filtering/UI, e.g. "holo" | "reverse_holo" |
            // "1st_edition" | "alt_art" | "parallel" — free string, no enum yet.
            $table->string('variant_type')->nullable();
            $table->string('rarity')->nullable();
            $table->string('artist')->nullable();
            $table->string('image_small', 512)->nullable();
            $table->string('image_large', 512)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['card_id', 'sort_order']);
            $table->index(['rarity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binder_card_variants');
    }
};
