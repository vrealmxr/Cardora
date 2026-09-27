<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Fusion World is the only locally-cached game whose variant discriminator
 * (source_variant_id, baked verbatim into variant_key and hence into the
 * image directory name) is a plain integer -- every other game's suffix is
 * alphabetic ("normal", "hyperspace", a content hash...). That happens to
 * make the on-disk path look like "cardKey:639", and confirmed by a live
 * isolated test (an identical two-file setup, one "thing:123", one
 * "thing:abc"), the host's platform serves the letters path as a static
 * file but rewrites the digits-only one to the SPA's index.html --
 * whatever WAF/edge rule is doing this reads "word:1234" as host:port
 * syntax and blocks it, regardless of directory/file permissions (checked
 * byte-for-byte identical between a working and a broken path first).
 *
 * This is purely a FILE-NAMING problem, not an identity one: variant_key
 * itself is untouched everywhere else (matching, external ids, etc) --
 * only the on-disk directory and the stored image_small/image_large paths
 * get a "v" prefix inserted before the numeric suffix so the path segment
 * is no longer digits-only.
 */
class FixFusionWorldImagePaths extends Command
{
    protected $signature = 'cardora:fix-fusionworld-image-paths {--dry-run}';

    protected $description = 'Rename Fusion World cached image directories so their colon-suffix is not purely numeric (blocked by the host platform)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('binder_card_variants')
            ->where('image_small', 'like', '/cards/dragon-ball-super-fusion-world/%')
            ->whereRaw("SUBSTRING_INDEX(variant_key, ':', -1) REGEXP '^[0-9]+$'")
            ->get(['id', 'variant_key', 'image_small', 'image_large']);

        $this->info("Found {$rows->count()} affected variants.");

        $renamed = 0;
        $missing = 0;
        $alreadyDone = 0;

        foreach ($rows as $row) {
            // .../{cardKey}:{digits}/front.ext -> .../{cardKey}:v{digits}/front.ext
            $oldRelative = $row->image_small;
            $newRelative = preg_replace('#:(\d+)/([^/]+)$#', ':v$1/$2', $oldRelative);

            if ($newRelative === $oldRelative) {
                $this->warn("Pattern didn't match, skipping: {$oldRelative}");

                continue;
            }

            $oldDir = public_path(dirname($oldRelative));
            $newDir = public_path(dirname($newRelative));

            if (File::isDirectory($newDir)) {
                $alreadyDone++;
            } elseif (! File::isDirectory($oldDir)) {
                $missing++;
                $this->warn("Old dir missing, can't rename: {$oldDir}");

                continue;
            } else {
                if (! $dryRun) {
                    File::moveDirectory($oldDir, $newDir);
                }
                $renamed++;
            }

            if (! $dryRun) {
                DB::table('binder_card_variants')->where('id', $row->id)->update([
                    'image_small' => $newRelative,
                    'image_large' => $newRelative,
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info('--- Report ---');
        $this->line("renamed: {$renamed}");
        $this->line("already_done: {$alreadyDone}");
        $this->line("old_dir_missing: {$missing}");
        if ($dryRun) {
            $this->warn('(dry-run: no directories renamed, no DB rows updated)');
        }

        return self::SUCCESS;
    }
}
