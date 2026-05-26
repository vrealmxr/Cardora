<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('pending_amount', 12, 2)->default(0);
            $table->decimal('available_amount', 12, 2)->default(0);
            $table->decimal('paid_out_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->unique(['seller_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_balances');
    }
};
