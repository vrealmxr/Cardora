<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('seller_id')->constrained()->nullOnDelete();
            $table->string('stripe_checkout_session_id')->nullable()->after('product_id')->index();
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_checkout_session_id')->index();
            $table->string('stripe_charge_id')->nullable()->after('stripe_payment_intent_id')->index();
            $table->string('stripe_transfer_id')->nullable()->after('stripe_charge_id')->index();
            $table->decimal('total_amount', 10, 2)->default(0)->after('total');
            $table->decimal('commission_amount', 10, 2)->default(0)->after('total_amount');
            $table->decimal('seller_amount', 10, 2)->default(0)->after('commission_amount');
            $table->timestamp('buyer_confirmed_at')->nullable()->after('disputed_at');
            $table->timestamp('auto_release_at')->nullable()->after('buyer_confirmed_at');
            $table->timestamp('released_at')->nullable()->after('auto_release_at');
            $table->timestamp('cancelled_at')->nullable()->after('released_at');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');

            $table->index(['status', 'auto_release_at'], 'orders_status_auto_release_index');
        });

        DB::table('orders')->update([
            'total_amount' => DB::raw('total'),
            'commission_amount' => DB::raw('service_fee'),
            'seller_amount' => DB::raw('(subtotal + shipping_total - service_fee)'),
        ]);

        DB::table('orders')
            ->whereIn('status', ['pending'])
            ->update([
                'status' => OrderStatus::PendingPayment->value,
                'escrow_status' => OrderStatus::PendingPayment->value,
            ]);

        DB::table('orders')
            ->whereIn('status', ['paid', 'shipped', 'delivered'])
            ->update([
                'status' => OrderStatus::PaidPendingRelease->value,
                'escrow_status' => OrderStatus::PaidPendingRelease->value,
            ]);

        DB::table('orders')
            ->where('status', 'completed')
            ->update([
                'status' => OrderStatus::Released->value,
                'escrow_status' => OrderStatus::Released->value,
                'released_at' => DB::raw('COALESCE(completed_at, updated_at, created_at)'),
            ]);

        DB::table('orders')
            ->where('status', 'disputed')
            ->update([
                'status' => OrderStatus::Disputed->value,
                'escrow_status' => OrderStatus::Disputed->value,
            ]);

        DB::table('orders')
            ->where('status', 'cancelled')
            ->update([
                'status' => OrderStatus::Cancelled->value,
                'escrow_status' => OrderStatus::Cancelled->value,
                'cancelled_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_auto_release_index');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn([
                'stripe_checkout_session_id',
                'stripe_payment_intent_id',
                'stripe_charge_id',
                'stripe_transfer_id',
                'total_amount',
                'commission_amount',
                'seller_amount',
                'buyer_confirmed_at',
                'auto_release_at',
                'released_at',
                'cancelled_at',
                'refunded_at',
            ]);
        });
    }
};
