<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('offered_title');
            $table->text('offered_description')->nullable();
            $table->string('offered_condition')->nullable();
            $table->decimal('offered_value', 10, 2);
            $table->longText('offered_images')->nullable();
            $table->longText('offered_metadata')->nullable();
            $table->text('request_message')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
            $table->index(['requester_user_id', 'status']);
            $table->index(['listing_owner_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_requests');
    }
};
