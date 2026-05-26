<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prize_listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->string('campaign_type', 50);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('prize_title');
            $table->string('prize_category', 100)->nullable();
            $table->string('prize_condition')->nullable();
            $table->decimal('prize_value', 10, 2)->nullable();
            $table->decimal('entry_price', 10, 2)->nullable();
            $table->unsignedInteger('entries_per_euro')->nullable();
            $table->decimal('target_amount', 12, 2)->nullable();
            $table->decimal('current_amount', 12, 2)->default(0);
            $table->unsignedInteger('target_entries')->nullable();
            $table->unsignedInteger('entries_issued')->default(0);
            $table->unsignedInteger('sold_entries')->default(0);
            $table->unsignedInteger('participants_count')->default(0);
            $table->unsignedInteger('max_entries_per_user')->nullable();
            $table->string('status', 50)->default('draft');
            $table->boolean('featured')->default(false);
            $table->boolean('requires_verification')->default(false);
            $table->boolean('shipping_covered')->default(false);
            $table->text('fairness_note')->nullable();
            $table->string('dispatch_window')->nullable();
            $table->longText('rules')->nullable();
            $table->longText('eligibility')->nullable();
            $table->longText('visual')->nullable();
            $table->longText('draw_result')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('draw_at')->nullable();
            $table->timestamp('drawn_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_type', 'status']);
            $table->index('host_user_id');
            $table->index('featured');
            $table->index('draw_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_campaigns');
    }
};

