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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('title_snapshot')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('minimum_offer', 10, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('available_quantity')->default(1);
            $table->string('condition')->nullable();
            $table->string('rarity')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('shipping_profile')->nullable();
            $table->longText('shipping_methods')->nullable();
            $table->string('dispatch_time')->nullable();
            $table->text('packaging_notes')->nullable();
            $table->string('availability')->default('available');
            $table->boolean('accept_offers')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->longText('attributes')->nullable();
            $table->longText('compliance_flags')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};

