<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('binder_card_id')
                ->nullable()
                ->after('category_id')
                ->constrained('binder_cards')
                ->nullOnDelete();
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('binder_alert_notified_at')->nullable()->after('followers_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('binder_card_id');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('binder_alert_notified_at');
        });
    }
};
