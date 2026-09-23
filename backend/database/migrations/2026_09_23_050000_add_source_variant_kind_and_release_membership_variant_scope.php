<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Piece TCG needed two refinements to the existing Variant and
     * Release/Membership layers, both additive:
     *
     * 1. binder_card_variants.source_variant_id / source_variant_kind:
     *    the upstream suffix ("_p1", "_r2", or none -- "base") that
     *    distinguishes one physical printing/artwork from another under
     *    the same base card number. Kept as plain source metadata, never
     *    the thing that decides canonical identity -- base card number +
     *    gameplay fields still fully determine which binder_card a variant
     *    belongs to, regardless of whether its suffix says "p" or "r".
     *
     * 2. binder_card_release_memberships.variant_id (nullable FK): for
     *    Yu-Gi-Oh! a release membership only ever needed to be card-level
     *    (the whole card ships in a box). One Piece needs finer grain --
     *    a specific ARTWORK (variant) is what actually ships in a specific
     *    product (e.g. a Premium Booster's own parallel art of an older
     *    card), while other memberships are still only known at the
     *    card level. variant_id stays nullable for that general case.
     *
     *    Uniqueness must now cover (card_id, variant_id, release_id), but
     *    a plain unique index treats NULL as always-distinct (MySQL/
     *    MariaDB), which would let card-level (variant_id NULL) duplicates
     *    slip through for the same card+release. A generated column
     *    collapses NULL to 0 for uniqueness purposes only, closing that
     *    gap without changing the nullable FK's own semantics.
     */
    public function up(): void
    {
        Schema::table('binder_card_variants', function (Blueprint $table) {
            $table->string('source_variant_id')->nullable()->after('variant_key'); // e.g. "ST01-006_r1", exactly as it appeared upstream
            $table->string('source_variant_kind')->nullable()->after('source_variant_id'); // "base" | "p" | "r" | ... -- metadata only, see docblock above

            $table->index(['source_variant_id']);
        });

        Schema::table('binder_card_release_memberships', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('card_id')->constrained('binder_card_variants')->nullOnDelete();
        });

        DB::statement(
            'ALTER TABLE binder_card_release_memberships
             ADD COLUMN variant_id_key BIGINT UNSIGNED AS (COALESCE(variant_id, 0)) STORED'
        );
        Schema::table('binder_card_release_memberships', function (Blueprint $table) {
            $table->dropUnique('binder_card_release_memberships_card_release_unique');
            $table->unique(['card_id', 'variant_id_key', 'release_id'], 'binder_card_release_memberships_card_variant_release_unique');
        });
    }

    public function down(): void
    {
        Schema::table('binder_card_release_memberships', function (Blueprint $table) {
            $table->dropUnique('binder_card_release_memberships_card_variant_release_unique');
        });
        DB::statement('ALTER TABLE binder_card_release_memberships DROP COLUMN variant_id_key');
        Schema::table('binder_card_release_memberships', function (Blueprint $table) {
            $table->unique(['card_id', 'release_id'], 'binder_card_release_memberships_card_release_unique');
            $table->dropConstrainedForeignId('variant_id');
        });

        Schema::table('binder_card_variants', function (Blueprint $table) {
            $table->dropIndex(['source_variant_id']);
            $table->dropColumn(['source_variant_id', 'source_variant_kind']);
        });
    }
};
