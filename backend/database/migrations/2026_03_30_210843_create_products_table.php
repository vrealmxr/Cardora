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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('subtitle')->nullable();
            $table->string('franchise')->nullable();
            $table->string('series')->nullable();
            $table->string('brand')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('language')->nullable();
            $table->string('set_name')->nullable();
            $table->string('item_number')->nullable();
            $table->string('product_type')->nullable();
            $table->text('description')->nullable();
            $table->longText('specifications')->nullable();
            $table->longText('tags')->nullable();
            $table->longText('media')->nullable();
            $table->text('authenticity_notes')->nullable();
            $table->boolean('is_authenticated')->default(false);
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

