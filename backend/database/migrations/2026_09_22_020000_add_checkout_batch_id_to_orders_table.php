<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correlates the multiple Order rows a single cart checkout can now produce (one order
     * per cart item, regardless of seller) back to the one Stripe Checkout Session that paid
     * for all of them.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_batch_id')->nullable()->after('order_number');
            $table->index('checkout_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['checkout_batch_id']);
            $table->dropColumn('checkout_batch_id');
        });
    }
};
