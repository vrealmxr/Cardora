<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('is_verified_seller');
            $table->string('admin_role')->nullable()->after('is_admin');
            $table->text('admin_notes')->nullable()->after('admin_role');
            $table->timestamp('admin_last_seen_at')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'admin_role',
                'admin_notes',
                'admin_last_seen_at',
            ]);
        });
    }
};
