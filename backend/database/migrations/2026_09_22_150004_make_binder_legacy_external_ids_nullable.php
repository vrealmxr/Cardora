<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rows created through the new (v2) canonical-card importer have no
     * TCGplayer identity at all — these two columns need to accept NULL so
     * we don't have to fabricate fake numeric IDs to satisfy a NOT NULL
     * that only ever meant "TCGplayer's id". MySQL unique indexes allow
     * multiple NULLs, so the existing uniqueness guarantee for rows that
     * DO have a real id is unaffected. Raw SQL (not ->change()) since
     * doctrine/dbal isn't installed in this project.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE binder_sets MODIFY external_group_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE binder_cards MODIFY external_product_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE binder_sets SET external_group_id = 0 WHERE external_group_id IS NULL');
        DB::statement('UPDATE binder_cards SET external_product_id = 0 WHERE external_product_id IS NULL');
        DB::statement('ALTER TABLE binder_sets MODIFY external_group_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE binder_cards MODIFY external_product_id BIGINT UNSIGNED NOT NULL');
    }
};
