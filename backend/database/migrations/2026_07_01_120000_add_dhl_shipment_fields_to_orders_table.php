<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_carrier')->nullable()->after('payment_method');
            $table->string('shipping_service')->nullable()->after('shipping_carrier');
            $table->string('shipment_status')->nullable()->after('shipping_service');
            $table->string('shipment_tracking_number')->nullable()->after('tracking_number');
            $table->string('shipment_reference')->nullable()->after('shipment_tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('placed_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->string('shipment_last_event_code')->nullable()->after('refunded_at');
            $table->text('shipment_last_event_description')->nullable()->after('shipment_last_event_code');
            $table->timestamp('shipment_last_event_at')->nullable()->after('shipment_last_event_description');
            $table->timestamp('shipment_synced_at')->nullable()->after('shipment_last_event_at');
            $table->longText('shipment_metadata')->nullable()->after('shipment_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_carrier',
                'shipping_service',
                'shipment_status',
                'shipment_tracking_number',
                'shipment_reference',
                'shipped_at',
                'delivered_at',
                'shipment_last_event_code',
                'shipment_last_event_description',
                'shipment_last_event_at',
                'shipment_synced_at',
                'shipment_metadata',
            ]);
        });
    }
};
