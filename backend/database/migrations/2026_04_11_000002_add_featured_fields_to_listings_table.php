<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('featured_until')->nullable()->after('is_featured');
            $table->foreignId('featured_payment_id')
                ->nullable()
                ->after('featured_until')
                ->constrained('featured_listing_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('featured_payment_id');
            $table->dropColumn('featured_until');
        });
    }
};
