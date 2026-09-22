<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('binder_games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // 'tcg' | 'sports' | 'gaming' — matches the three groupings on the
            // game picker screen.
            $table->string('category');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('binder_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('binder_games')->cascadeOnDelete();
            // TCGplayer's own "groupId" — the stable link between sets.csv and
            // each Cards/*.csv file's groupId column.
            $table->unsignedBigInteger('external_group_id');
            $table->string('slug');
            $table->string('name');
            $table->string('abbreviation')->nullable();
            $table->timestamp('released_at')->nullable();
            // Denormalized, refreshed at the end of each import run — avoids a
            // COUNT(*) join every time the set list renders.
            $table->unsignedInteger('card_count')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'external_group_id']);
            $table->index(['game_id', 'slug']);
            $table->index(['game_id', 'released_at']);
        });

        Schema::create('binder_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('binder_sets')->cascadeOnDelete();
            // Denormalized game_id so "all my Pokémon cards" style queries
            // don't need to join through binder_sets.
            $table->foreignId('game_id')->constrained('binder_games')->cascadeOnDelete();
            // TCGplayer's productId — globally unique across every game/set.
            $table->unsignedBigInteger('external_product_id')->unique();
            $table->string('name');
            $table->string('clean_name')->nullable();
            // e.g. "001/086" — pulled out of the messy extendedData blob at
            // import time so the UI never has to parse it.
            $table->string('number')->nullable();
            $table->string('rarity')->nullable();
            $table->string('card_type')->nullable();
            $table->string('image_url', 512)->nullable();
            $table->timestamps();

            $table->index(['set_id']);
            $table->index(['game_id', 'rarity']);
            $table->index(['set_id', 'name']);
        });

        Schema::create('binder_user_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('binder_cards')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'card_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('binder_user_cards');
        Schema::dropIfExists('binder_cards');
        Schema::dropIfExists('binder_sets');
        Schema::dropIfExists('binder_games');
    }
};
