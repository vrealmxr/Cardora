<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trade_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('proposer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dispute_opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending_funding');
            $table->decimal('owner_declared_value', 10, 2);
            $table->decimal('proposer_declared_value', 10, 2);
            $table->decimal('deposit_amount', 10, 2);
            $table->decimal('fee_rate', 5, 4)->default(0.0500);
            $table->decimal('owner_gross_amount', 10, 2);
            $table->decimal('proposer_gross_amount', 10, 2);
            $table->decimal('owner_fee_amount', 10, 2)->nullable();
            $table->decimal('proposer_fee_amount', 10, 2)->nullable();
            $table->decimal('owner_net_amount', 10, 2)->nullable();
            $table->decimal('proposer_net_amount', 10, 2)->nullable();
            $table->decimal('platform_fee_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('owner_stripe_checkout_session_id')->nullable()->index();
            $table->string('proposer_stripe_checkout_session_id')->nullable()->index();
            $table->string('owner_stripe_payment_intent_id')->nullable()->index();
            $table->string('proposer_stripe_payment_intent_id')->nullable()->index();
            $table->string('owner_stripe_charge_id')->nullable()->index();
            $table->string('proposer_stripe_charge_id')->nullable()->index();
            $table->timestamp('owner_paid_at')->nullable();
            $table->timestamp('proposer_paid_at')->nullable();
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('owner_released_at')->nullable();
            $table->timestamp('proposer_released_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('resolution')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->longText('payout_metadata')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->index(['proposer_user_id', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_deals');
    }
};
