<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('entries')->default(1);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('source_type', 50);
            $table->string('source_reference')->nullable();
            $table->string('status', 50)->default('confirmed');
            $table->longText('metadata')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->timestamps();

            $table->index(['draw_campaign_id', 'user_id']);
            $table->index(['draw_campaign_id', 'status']);
            $table->index('entered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_entries');
    }
};

