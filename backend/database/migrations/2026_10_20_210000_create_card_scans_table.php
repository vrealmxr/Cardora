<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('card_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('request_key');
            $table->string('category', 32);
            $table->string('state', 24)->default('queued')->index();
            $table->string('review_status', 24)->default('pending');
            $table->boolean('training_opt_in')->default(false);
            $table->string('policy_version', 80);
            $table->json('images');
            $table->json('prediction')->nullable();
            $table->json('review')->nullable();
            $table->json('clearance')->nullable();
            $table->json('provenance');
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('error_code', 60)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
        });
    }
    public function down(): void { Schema::dropIfExists('card_scans'); }
};
