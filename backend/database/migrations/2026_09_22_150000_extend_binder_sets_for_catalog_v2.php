<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binder_sets', function (Blueprint $table) {
            $table->string('set_key')->nullable()->after('slug');
            // Provenance — which import source populated this row, and that
            // source's own set identifier (replaces relying on TCGplayer's
            // external_group_id as the only source-of-truth link).
            $table->string('source')->nullable()->after('set_key');
            $table->string('source_set_id')->nullable()->after('source');
            $table->string('source_url', 512)->nullable()->after('source_set_id');
            // Official product code, e.g. "sv3pt5" / TCGplayer "MEW" — distinct
            // from the human-readable name.
            $table->string('set_code')->nullable()->after('abbreviation');
            // 'main' | 'promo' | 'special_edition' | 'sealed' | ... — free string,
            // used to explain sets that legitimately have zero standalone cards.
            $table->string('set_type')->nullable()->after('set_code');
            $table->string('language', 8)->default('EN')->after('set_type');
            $table->string('region')->nullable()->after('language');
            // Two different, often-confused counts (e.g. Pokémon 151: 165
            // official vs 207 printed with secret rares) — see project notes.
            $table->unsignedInteger('official_total')->nullable()->after('card_count');
            $table->unsignedInteger('printed_total')->nullable()->after('official_total');
        });

        Schema::table('binder_sets', function (Blueprint $table) {
            $table->unique('set_key');
        });
    }

    public function down(): void
    {
        Schema::table('binder_sets', function (Blueprint $table) {
            $table->dropUnique(['set_key']);
            $table->dropColumn([
                'set_key', 'source', 'source_set_id', 'source_url',
                'set_code', 'set_type', 'language', 'region',
                'official_total', 'printed_total',
            ]);
        });
    }
};
