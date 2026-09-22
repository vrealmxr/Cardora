<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One variant can map to several external providers over time
     * (TCGplayer productId, Scrydex id, Cardmarket id, an eBay search
     * mapping, ...) without ever touching binder_card_variants' own
     * schema — this is what a single "external_id" column on the card
     * couldn't support once one card has more than one provider.
     */
    public function up(): void
    {
        Schema::create('binder_card_external_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('binder_card_variants')->cascadeOnDelete();
            $table->string('provider'); // 'tcgplayer' | 'scrydex' | 'cardmarket' | 'ebay' | ...
            $table->string('external_id');
            $table->string('external_url', 512)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binder_card_external_ids');
    }
};
