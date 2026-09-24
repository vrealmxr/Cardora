<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit "does this game show in the Binder UI" switch, independent of
     * catalog_status (data readiness) -- e.g. a legacy game can have real
     * card rows (catalog_status=legacy) and still be hidden from the Binder
     * entirely, while a game with zero cards yet can be pre-enabled ahead
     * of its v2 import landing.
     */
    public function up(): void
    {
        Schema::table('binder_games', function (Blueprint $table) {
            $table->boolean('binder_enabled')->default(false)->after('catalog_status');
        });

        DB::table('binder_games')
            ->whereIn('slug', [
                'pokemon',
                'yugioh',
                'magic-the-gathering',
                'one-piece',
                'disney-lorcana',
                'riftbound',
                'dragon-ball-super-fusion-world',
                'star-wars-unlimited',
                'nba',
                'soccer',
                'euroleague',
            ])
            ->update(['binder_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('binder_games', function (Blueprint $table) {
            $table->dropColumn('binder_enabled');
        });
    }
};
