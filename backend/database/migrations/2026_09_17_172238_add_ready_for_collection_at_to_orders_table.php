<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carrier-agnostic "arrived at pickup point, not yet collected" timestamp — distinct from
     * delivered_at, which only fires once the buyer actually collects the parcel (relevant for
     * locker/service-point carriers like BoxNow, where those are two separate real-world events).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ready_for_collection_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ready_for_collection_at');
        });
    }
};
