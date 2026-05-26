<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('body_masked')->nullable()->after('body');
            $table->string('moderation_status')->default('clean')->after('metadata');
            $table->longText('moderation_flags')->nullable()->after('moderation_status');
            $table->unsignedSmallInteger('moderation_score')->default(0)->after('moderation_flags');
            $table->boolean('requires_admin_review')->default(false)->after('moderation_score');
            $table->timestamp('reviewed_at')->nullable()->after('requires_admin_review');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['requires_admin_review', 'moderation_status'], 'messages_review_status_idx');
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_review_status_idx');
            $table->dropIndex(['read_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'body_masked',
                'moderation_status',
                'moderation_flags',
                'moderation_score',
                'requires_admin_review',
                'reviewed_at',
            ]);
        });
    }
};
