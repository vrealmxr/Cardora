<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('page_key');
            $table->string('locale', 2);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('h1')->nullable();
            $table->timestamps();

            $table->unique(['page_key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_seo_settings');
    }
};
