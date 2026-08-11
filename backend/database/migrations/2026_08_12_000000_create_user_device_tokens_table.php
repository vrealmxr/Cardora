<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the `user_device_tokens` table for git/migrations.
 *
 * NOTE: on the production server this table already exists — it was created
 * manually (outside Laravel's migration system, no corresponding migration
 * row). The Schema::hasTable() guard below makes this migration a no-op
 * there, while still creating the table correctly on any fresh environment
 * (local, staging, CI). See docs/deploy notes for how it was reconciled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_device_tokens')) {
            return;
        }

        Schema::create('user_device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('ios');
            $table->string('token', 512);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'token']);
            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_tokens');
    }
};
