<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('stripe_transfer_id')->nullable()->after('reference')->index();
            $table->string('stripe_payout_id')->nullable()->after('stripe_transfer_id')->unique();
            $table->string('connected_account_id')->nullable()->after('stripe_payout_id')->index();
            $table->timestamp('requested_at')->nullable()->after('initiated_at');
            $table->timestamp('processed_at')->nullable()->after('completed_at');
            $table->timestamp('arrival_date')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_transfer_id',
                'stripe_payout_id',
                'connected_account_id',
                'requested_at',
                'processed_at',
                'arrival_date',
            ]);
        });
    }
};
