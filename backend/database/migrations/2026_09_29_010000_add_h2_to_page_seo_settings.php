<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_seo_settings', function (Blueprint $table) {
            $table->string('h2')->nullable()->after('h1');
        });
    }

    public function down(): void
    {
        Schema::table('page_seo_settings', function (Blueprint $table) {
            $table->dropColumn('h2');
        });
    }
};
