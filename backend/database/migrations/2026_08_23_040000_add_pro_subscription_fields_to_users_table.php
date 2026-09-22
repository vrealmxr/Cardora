<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('is_admin')->index();
            $table->string('pro_status')->nullable()->after('stripe_customer_id');
            $table->timestamp('pro_current_period_end')->nullable()->after('pro_status');
            $table->boolean('pro_cancel_at_period_end')->default(false)->after('pro_current_period_end');
            // Set once, permanently, the moment a trial-bearing checkout completes.
            // Never cleared — this is what stops cancel -> resubscribe from granting
            // a second free trial.
            $table->timestamp('pro_trial_used_at')->nullable()->after('pro_cancel_at_period_end');
            // One free featured-listing credit per PRO billing period. Reset to true
            // on every successful invoice.paid for a PRO subscription.
            $table->boolean('pro_featured_credit_available')->default(false)->after('pro_trial_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'pro_status',
                'pro_current_period_end',
                'pro_cancel_at_period_end',
                'pro_trial_used_at',
                'pro_featured_credit_available',
            ]);
        });
    }
};
