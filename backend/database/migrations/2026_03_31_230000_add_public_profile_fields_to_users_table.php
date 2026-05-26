<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('handle')->nullable()->unique()->after('display_name');
            $table->string('collector_tagline')->nullable()->after('bio');
            $table->longText('profile_cover')->nullable()->after('avatar_url');
            $table->string('profile_visibility')->default('public')->after('collector_tagline');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'handle',
                'collector_tagline',
                'profile_cover',
                'profile_visibility',
            ]);
        });
    }
};

