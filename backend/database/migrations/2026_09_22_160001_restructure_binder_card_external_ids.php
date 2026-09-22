<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original table only pointed at a variant (variant_id FK). Two
     * problems in practice: (1) some providers (e.g. Scrydex) key a card
     * at the canonical level, not per-printing, so a variant-only FK can't
     * represent that; (2) a single provider can expose more than one ID
     * *type* for the same entity (TCGplayer productId vs its per-condition
     * SKU ids) — external_type captures which kind this row is.
     *
     * No real rows exist yet (structural-only migration, ran before any
     * catalog v2 import), so this replaces rather than migrates data.
     * entity_key stores the business key (card_key or variant_key) rather
     * than a numeric FK, matching how the importer already resolves
     * everything else by key instead of DB id.
     */
    public function up(): void
    {
        Schema::table('binder_card_external_ids', function (Blueprint $table) {
            $table->dropUnique(['provider', 'external_id']);
            $table->dropForeign(['variant_id']);
            $table->dropIndex(['variant_id']);
            $table->dropColumn('variant_id');
        });

        Schema::table('binder_card_external_ids', function (Blueprint $table) {
            $table->string('entity_type')->after('id'); // 'card' | 'variant'
            $table->string('entity_key')->after('entity_type');
            $table->string('external_type')->nullable()->after('external_id'); // 'product_id' | 'card_id' | 'sku_id' | ...

            $table->index(['entity_type', 'entity_key']);
            $table->unique(['provider', 'external_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('binder_card_external_ids', function (Blueprint $table) {
            $table->dropUnique(['provider', 'external_type', 'external_id']);
            $table->dropIndex(['entity_type', 'entity_key']);
            $table->dropColumn(['entity_type', 'entity_key', 'external_type']);
        });

        Schema::table('binder_card_external_ids', function (Blueprint $table) {
            $table->foreignId('variant_id')->after('id')->constrained('binder_card_variants')->cascadeOnDelete();
            $table->index(['variant_id']);
            $table->unique(['provider', 'external_id']);
        });
    }
};
