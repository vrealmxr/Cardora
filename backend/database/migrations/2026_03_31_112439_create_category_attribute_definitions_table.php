<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxonomy_id')->nullable()->constrained('category_taxonomies')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('field_type', 50)->default('text');
            $table->longText('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('applies_to_product')->default(true);
            $table->boolean('applies_to_listing')->default(true);
            $table->string('status', 50)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->longText('validation_rules')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['taxonomy_id', 'status']);
            $table->unique(['category_id', 'slug'], 'category_attribute_definitions_unique_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_definitions');
    }
};

