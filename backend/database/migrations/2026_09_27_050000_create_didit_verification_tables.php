<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source')->default('didit');
            $table->string('status')->default('Not Started');
            $table->uuid('current_session_id')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('migrated_at')->nullable();
            $table->json('legacy_snapshot')->nullable();
            $table->timestamps();
        });
        Schema::create('didit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_id')->unique();
            $table->uuid('workflow_id');
            $table->string('environment');
            $table->string('status');
            $table->text('verification_url');
            $table->unsignedBigInteger('provider_updated_at')->default(0);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->string('notice_version');
            $table->string('consent_locale', 2);
            $table->timestamp('consented_at');
            $table->timestamp('retention_due_at')->index();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('session_deleted_at')->nullable();
            $table->string('deletion_outcome')->nullable();
            $table->unsignedInteger('deletion_attempts')->default(0);
            $table->timestamp('deletion_attempted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('didit_webhook_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->uuid('session_id')->index();
            $table->string('status');
            $table->string('outcome');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('didit_webhook_events');
        Schema::dropIfExists('didit_sessions');
        Schema::dropIfExists('identity_verifications');
    }
};
