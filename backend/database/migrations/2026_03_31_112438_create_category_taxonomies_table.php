<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_taxonomies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('category_taxonomies')->nullOnDelete();
            $table->string('taxonomy_type', 50)->default('item_type');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('status', 50)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'taxonomy_type']);
            $table->index(['taxonomy_type', 'status']);
            $table->unique(['category_id', 'taxonomy_type', 'slug'], 'category_taxonomies_unique_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_taxonomies');
    }
};

