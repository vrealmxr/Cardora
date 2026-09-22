<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'binder_ext_ids_provider_type_id_unique';

    private const LOOKUP_INDEX = 'binder_card_external_ids_entity_type_entity_key_index';

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
     *
     * Every step is guarded (hasColumn / indexExists) because a first
     * production run partially applied this migration: the default unique
     * index name (69 chars: "..._provider_external_type_external_id_unique")
     * exceeded MySQL's 64-char identifier limit and failed *after* the
     * column-drop and column-add statements had already committed (MySQL
     * DDL isn't transactional). This version is safe to run both from a
     * clean state and from that partially-applied state.
     */
    public function up(): void
    {
        if (Schema::hasColumn('binder_card_external_ids', 'variant_id')) {
            Schema::table('binder_card_external_ids', function (Blueprint $table) {
                $table->dropUnique(['provider', 'external_id']);
                $table->dropForeign(['variant_id']);
                $table->dropIndex(['variant_id']);
                $table->dropColumn('variant_id');
            });
        }

        Schema::table('binder_card_external_ids', function (Blueprint $table) {
            if (! Schema::hasColumn('binder_card_external_ids', 'entity_type')) {
                $table->string('entity_type')->after('id'); // 'set' | 'card' | 'variant'
            }
            if (! Schema::hasColumn('binder_card_external_ids', 'entity_key')) {
                $table->string('entity_key')->after('entity_type');
            }
            if (! Schema::hasColumn('binder_card_external_ids', 'external_type')) {
                $table->string('external_type')->nullable()->after('external_id'); // 'product_id' | 'card_id' | 'sku_id' | 'expansion_id' | ...
            }
        });

        if (! $this->indexExists('binder_card_external_ids', self::LOOKUP_INDEX)) {
            Schema::table('binder_card_external_ids', function (Blueprint $table) {
                $table->index(['entity_type', 'entity_key'], self::LOOKUP_INDEX);
            });
        }

        if (! $this->indexExists('binder_card_external_ids', self::UNIQUE_INDEX)) {
            Schema::table('binder_card_external_ids', function (Blueprint $table) {
                $table->unique(['provider', 'external_type', 'external_id'], self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::table('binder_card_external_ids', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_INDEX);
            $table->dropIndex(self::LOOKUP_INDEX);
            $table->dropColumn(['entity_type', 'entity_key', 'external_type']);
        });

        Schema::table('binder_card_external_ids', function (Blueprint $table) {
            $table->foreignId('variant_id')->after('id')->constrained('binder_card_variants')->cascadeOnDelete();
            $table->index(['variant_id']);
            $table->unique(['provider', 'external_id']);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
