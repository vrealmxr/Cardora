<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive layer, deliberately separate from binder_cards.set_id
     * (untouched, stays the primary/canonical checklist grouping — nothing
     * about existing code changes). This expresses a second, real-world
     * dimension the existing Set→Card→Variant model can't: the SAME
     * physical printing legitimately being sold as part of more than one
     * retail product/release (e.g. Blue-Eyes White Dragon KACB-EN001 Ultra
     * Rare shipped in both "Kaiba's Collector Box" (NA) and "Yugi & Kaiba
     * Collector Box" (EU) — one canonical card, two release memberships).
     *
     * The distinction that matters: a physical printing change (different
     * rarity, region-locked print, etc.) is still a Variant. The same
     * physical printing distributed in another product is a release
     * membership — it must never smuggle a second copy of the canonical
     * card into existence.
     */
    public function up(): void
    {
        Schema::create('binder_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('binder_games')->cascadeOnDelete();
            $table->string('release_key')->unique();
            $table->string('release_name');
            // e.g. "collector_box" | "limited_collectors_edition" | "promo_bundle" — free string, no enum yet.
            $table->string('release_type');
            $table->string('region_code')->nullable(); // characterizes the RELEASE, not the card/variant -- see migration docblock
            $table->timestamp('released_at')->nullable();
            $table->string('source_provider')->nullable();
            $table->string('source_external_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['game_id']);
        });

        Schema::create('binder_card_release_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('binder_cards')->cascadeOnDelete();
            $table->foreignId('release_id')->constrained('binder_releases')->cascadeOnDelete();
            // e.g. "promo_inclusion" | "primary_product" — free string, no enum yet.
            $table->string('membership_type')->nullable();
            $table->string('source_provider')->nullable();
            $table->string('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['card_id', 'release_id'], 'binder_card_release_memberships_card_release_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binder_card_release_memberships');
        Schema::dropIfExists('binder_releases');
    }
};
