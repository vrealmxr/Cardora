<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * official_total/printed_total were ambiguous across games (e.g. does
     * "printed" mean "physically printed count" or "everything on the
     * checklist including secret rares"?). Renamed before any real data
     * was imported into these columns, so this is a pure rename — no
     * backfill needed. Raw SQL (not ->renameColumn()) since doctrine/dbal
     * isn't installed in this project.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE binder_sets CHANGE official_total base_total INT UNSIGNED NULL');
        DB::statement('ALTER TABLE binder_sets CHANGE printed_total numbered_total INT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE binder_sets CHANGE base_total official_total INT UNSIGNED NULL');
        DB::statement('ALTER TABLE binder_sets CHANGE numbered_total printed_total INT UNSIGNED NULL');
    }
};
