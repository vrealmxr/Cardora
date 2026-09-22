<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per card: the last price we actually alerted PRO watchers
        // about, so small back-and-forth fluctuations don't spam them.
        Schema::create('binder_price_alert_state', function (Blueprint $table) {
            $table->foreignId('binder_card_id')->primary()->constrained('binder_cards')->cascadeOnDelete();
            $table->decimal('last_alert_price', 10, 2);
            $table->timestamp('last_alert_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binder_price_alert_state');
    }
};
