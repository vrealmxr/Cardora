<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sets a user explicitly wants listing alerts for, even before they
        // own a single card from it — separate from ownership, which grants
        // the same alerts implicitly (see BinderSetAlertService).
        Schema::create('binder_watched_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('set_id')->constrained('binder_sets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binder_watched_sets');
    }
};
