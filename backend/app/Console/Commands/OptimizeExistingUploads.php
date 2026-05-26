<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\CollectionEntry;
use App\Models\DrawCampaign;
use App\Models\Product;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Services\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OptimizeExistingUploads extends Command
{
    protected $signature = 'uploads:optimize-existing
        {--only=* : Targets: products,collections,users,verifications,blog,campaigns,filesystem}
        {--disk=public : Default disk for records without explicit disk}
        {--chunk=100 : Records per chunk for DB traversal}
        {--limit=0 : Max records per target (0 = no limit)}
        {--dir=uploads : Base directory for filesystem scan target}
        {--delete-original : Delete old image when converted to a new .webp path}';

    protected $description = 'Optimize existing uploaded images and update stored references to optimized files.';

    protected ImageUploadService $imageUploadService;

    protected string $defaultDisk = 'public';

    protected int $chunkSize = 100;

    protected int $limitPerTarget = 0;

    protected bool $deleteOriginals = false;

    protected array $cache = [];

    protected array $deletedPaths = [];

    protected array $summary = [];

    public function handle(ImageUploadService $imageUploadService): int
    {
        $this->imageUploadService = $imageUploadService;
        $this->defaultDisk = (string) $this->option('disk');
        $this->chunkSize = max(1, (int) $this->option('chunk'));
        $this->limitPerTarget = max(0, (int) $this->option('limit'));
        $this->deleteOriginals = (bool) $this->option('delete-original');

        $targets = $this->resolveTargets();
        if ($targets === []) {
            $this->error('No valid targets selected.');

            return self::FAILURE;
        }

        $this->line(sprintf('Targets: %s', implode(', ', $targets)));
        $this->line(sprintf('Default disk: %s', $this->defaultDisk));
        $this->line(sprintf('Chunk size: %d', $this->chunkSize));
        if ($this->limitPerTarget > 0) {
            $this->line(sprintf('Limit per target: %d', $this->limitPerTarget));
        }
        $this->newLine();

        foreach ($targets as $target) {
            $this->summary[$target] = $this->blankStats();
            $this->info(sprintf('Processing target [%s]...', $target));

            try {
                match ($target) {
                    'products' => $this->processProducts(),
                    'collections' => $this->processCollections(),
                    'users' => $this->processUsers(),
                    'verifications' => $this->processVerificationDocuments(),
                    'blog' => $this->processBlogPosts(),
                    'campaigns' => $this->processCampaigns(),
                    'filesystem' => $this->processFilesystemScan((string) $this->option('dir')),
                    default => null,
                };
            } catch (\Throwable $exception) {
                $this->summary[$target]['errors']++;
                $this->error(sprintf('[%s] %s', $target, $exception->getMessage()));
            }

            $this->line(sprintf(
                'Done [%s] scanned=%d updated=%d optimized=%d skipped=%d errors=%d',
                $target,
                $this->summary[$target]['scanned'],
                $this->summary[$target]['updated'],
                $this->summary[$target]['optimized'],
                $this->summary[$target]['skipped'],
                $this->summary[$target]['errors']
            ));
            $this->newLine();
        }

        $this->outputSummaryTable();

        return self::SUCCESS;
    }

    protected function processProducts(): void
    {
        $query = Product::query()
            ->whereNotNull('media')
            ->orderBy('id');

        $this->iterateModels('products', $query, function (Product $product): void {
            $this->summary['products']['scanned']++;
            $media = is_array($product->media) ? $product->media : [];

            [$updatedMedia, $changed, $optimizedCount, $skippedCount] = $this->optimizeMediaArray($media);
            $this->summary['products']['optimized'] += $optimizedCount;
            $this->summary['products']['skipped'] += $skippedCount;

            if (! $changed) {
                return;
            }

            $product->forceFill(['media' => $updatedMedia])->save();
            $this->summary['products']['updated']++;
        });
    }

    protected function processCollections(): void
    {
        $query = CollectionEntry::query()
            ->whereNotNull('media')
            ->orderBy('id');

        $this->iterateModels('collections', $query, function (CollectionEntry $entry): void {
            $this->summary['collections']['scanned']++;
            $media = is_array($entry->media) ? $entry->media : [];

            [$updatedMedia, $changed, $optimizedCount, $skippedCount] = $this->optimizeMediaArray($media);
            $this->summary['collections']['optimized'] += $optimizedCount;
            $this->summary['collections']['skipped'] += $skippedCount;

            if (! $changed) {
                return;
            }

            $entry->forceFill(['media' => $updatedMedia])->save();
            $this->summary['collections']['updated']++;
        });
    }

    protected function processUsers(): void
    {
        $query = User::query()
            ->whereNotNull('avatar_url')
            ->where('avatar_url', '!=', '')
            ->orderBy('id');

        $this->iterateModels('users', $query, function (User $user): void {
            $this->summary['users']['scanned']++;

            $avatarUrl = is_string($user->getRawOriginal('avatar_url'))
                ? trim((string) $user->getRawOriginal('avatar_url'))
                : '';

            if ($avatarUrl === '') {
                $this->summary['users']['skipped']++;

                return;
            }

            $path = $this->extractStoragePath($avatarUrl);
            if (! $path) {
                $this->summary['users']['skipped']++;

                return;
            }

            [$meta, $wasOptimized] = $this->optimizePath($this->defaultDisk, $path);
            if (! $meta) {
                $this->summary['users']['skipped']++;

                return;
            }

            if ($wasOptimized) {
                $this->summary['users']['optimized']++;
            }

            if (($meta['url'] ?? null) === $avatarUrl) {
                return;
            }

            $user->forceFill(['avatar_url' => $meta['url']])->save();
            $this->summary['users']['updated']++;
        });
    }

    protected function processVerificationDocuments(): void
    {
        $query = VerificationDocument::query()
            ->whereNotNull('storage_path')
            ->where('storage_path', '!=', '')
            ->orderBy('id');

        $this->iterateModels('verifications', $query, function (VerificationDocument $document): void {
            $this->summary['verifications']['scanned']++;

            $disk = is_string($document->storage_disk) && trim($document->storage_disk) !== ''
                ? $document->storage_disk
                : $this->defaultDisk;
            $path = ltrim(trim((string) $document->storage_path), '/');
            if ($path === '') {
                $this->summary['verifications']['skipped']++;

                return;
            }

            [$meta, $wasOptimized] = $this->optimizePath($disk, $path);
            if (! $meta) {
                $this->summary['verifications']['skipped']++;

                return;
            }

            if ($wasOptimized) {
                $this->summary['verifications']['optimized']++;
            }

            $newValues = [
                'storage_path' => $meta['path'],
                'mime_type' => $meta['mime_type'] ?? 'image/webp',
                'file_size' => $meta['file_size'],
            ];

            if (
                $document->storage_path === $newValues['storage_path']
                && $document->mime_type === $newValues['mime_type']
                && ((int) $document->file_size) === ((int) ($newValues['file_size'] ?? 0))
            ) {
                return;
            }

            $document->forceFill($newValues)->save();
            $this->summary['verifications']['updated']++;
        });
    }

    protected function processBlogPosts(): void
    {
        $query = BlogPost::query()
            ->whereNotNull('cover_media')
            ->orderBy('id');

        $this->iterateModels('blog', $query, function (BlogPost $post): void {
            $this->summary['blog']['scanned']++;
            $coverMedia = is_array($post->cover_media) ? $post->cover_media : [];

            [$updatedItem, $changed, $optimizedCount, $skippedCount] = $this->optimizeSingleMediaPayload($coverMedia);
            $this->summary['blog']['optimized'] += $optimizedCount;
            $this->summary['blog']['skipped'] += $skippedCount;

            if (! $changed) {
                return;
            }

            $post->forceFill(['cover_media' => $updatedItem])->save();
            $this->summary['blog']['updated']++;
        });
    }

    protected function processCampaigns(): void
    {
        $query = DrawCampaign::query()
            ->whereNotNull('visual')
            ->orderBy('id');

        $this->iterateModels('campaigns', $query, function (DrawCampaign $campaign): void {
            $this->summary['campaigns']['scanned']++;
            $visual = is_array($campaign->visual) ? $campaign->visual : [];
            $path = $this->extractStoragePath(
                is_string($visual['imagePath'] ?? null)
                    ? $visual['imagePath']
                    : (is_string($visual['imageUrl'] ?? null) ? $visual['imageUrl'] : '')
            );

            if (! $path) {
                $this->summary['campaigns']['skipped']++;

                return;
            }

            [$meta, $wasOptimized] = $this->optimizePath($this->defaultDisk, $path);
            if (! $meta) {
                $this->summary['campaigns']['skipped']++;

                return;
            }

            if ($wasOptimized) {
                $this->summary['campaigns']['optimized']++;
            }

            $updatedVisual = $visual;
            $updatedVisual['imagePath'] = $meta['path'];
            $updatedVisual['imageUrl'] = $meta['url'];

            if ($updatedVisual === $visual) {
                return;
            }

            $campaign->forceFill(['visual' => $updatedVisual])->save();
            $this->summary['campaigns']['updated']++;
        });
    }

    protected function processFilesystemScan(string $directory): void
    {
        $directory = trim($directory, '/');
        $files = Storage::disk($this->defaultDisk)->allFiles($directory);

        foreach ($files as $path) {
            if ($this->limitPerTarget > 0 && $this->summary['filesystem']['scanned'] >= $this->limitPerTarget) {
                break;
            }

            $normalizedPath = ltrim((string) $path, '/');
            if (! $this->isOptimizableExtension($normalizedPath)) {
                $this->summary['filesystem']['skipped']++;

                continue;
            }

            $this->summary['filesystem']['scanned']++;
            [$meta, $wasOptimized] = $this->optimizePath($this->defaultDisk, $normalizedPath);

            if (! $meta) {
                $this->summary['filesystem']['skipped']++;

                continue;
            }

            if ($wasOptimized) {
                $this->summary['filesystem']['optimized']++;
            }

            if (($meta['target_changed'] ?? false) || ($meta['optimized'] ?? false)) {
                $this->summary['filesystem']['updated']++;
            }
        }
    }

    protected function optimizeMediaArray(array $media): array
    {
        $changed = false;
        $optimizedCount = 0;
        $skippedCount = 0;

        $updated = collect($media)
            ->map(function ($item) use (&$changed, &$optimizedCount, &$skippedCount) {
                [$updatedItem, $itemChanged, $itemOptimized, $itemSkipped] = $this->optimizeSingleMediaPayload($item);

                if ($itemChanged) {
                    $changed = true;
                }
                $optimizedCount += $itemOptimized;
                $skippedCount += $itemSkipped;

                return $updatedItem;
            })
            ->all();

        return [$updated, $changed, $optimizedCount, $skippedCount];
    }

    protected function optimizeSingleMediaPayload(mixed $payload): array
    {
        if (is_string($payload)) {
            $originalValue = $payload;
            $path = $this->extractStoragePath($payload);
            if (! $path) {
                return [$payload, false, 0, 1];
            }

            [$meta, $wasOptimized] = $this->optimizePath($this->defaultDisk, $path);
            if (! $meta) {
                return [$payload, false, 0, 1];
            }

            $updatedValue = $this->looksLikeUrl($originalValue)
                ? (string) ($meta['url'] ?? $originalValue)
                : (string) ($meta['path'] ?? $originalValue);

            return [$updatedValue, $updatedValue !== $originalValue, $wasOptimized ? 1 : 0, 0];
        }

        if (! is_array($payload)) {
            return [$payload, false, 0, 1];
        }

        $item = $payload;
        $disk = $this->resolveDiskFromPayload($item);
        $path = $this->resolvePathFromPayload($item);

        if (! $path) {
            return [$payload, false, 0, 1];
        }

        [$meta, $wasOptimized] = $this->optimizePath($disk, $path);
        if (! $meta) {
            return [$payload, false, 0, 1];
        }

        $updated = $item;
        $updatedPath = (string) ($meta['path'] ?? $path);
        $updatedUrl = (string) ($meta['url'] ?? (Storage::disk($disk)->url($updatedPath)));
        $updatedPreviewPath = is_string($meta['preview_path'] ?? null) ? $meta['preview_path'] : null;
        $updatedPreviewUrl = is_string($meta['preview_url'] ?? null) ? $meta['preview_url'] : null;

        if (array_key_exists('path', $updated) || (! array_key_exists('path', $updated) && ! array_key_exists('storage_path', $updated))) {
            $updated['path'] = $updatedPath;
        }
        if (array_key_exists('storage_path', $updated)) {
            $updated['storage_path'] = $updatedPath;
        }

        if (array_key_exists('disk', $updated) || (! array_key_exists('disk', $updated) && ! array_key_exists('storage_disk', $updated))) {
            $updated['disk'] = $disk;
        }
        if (array_key_exists('storage_disk', $updated)) {
            $updated['storage_disk'] = $disk;
        }

        if (array_key_exists('url', $updated) || (! array_key_exists('url', $updated) && ! array_key_exists('thumb_url', $updated))) {
            $updated['url'] = $updatedUrl;
        }
        if (array_key_exists('thumb_url', $updated)) {
            $updated['thumb_url'] = $updatedPreviewUrl ?: $updatedUrl;
        }
        if (array_key_exists('preview_url', $updated)) {
            $updated['preview_url'] = $updatedPreviewUrl;
        }
        if (array_key_exists('preview_path', $updated)) {
            $updated['preview_path'] = $updatedPreviewPath;
        }
        if (array_key_exists('mime_type', $updated)) {
            $updated['mime_type'] = 'image/webp';
        }
        if (array_key_exists('file_size', $updated)) {
            $updated['file_size'] = $meta['file_size'] ?? null;
        }
        if (array_key_exists('width', $updated) && array_key_exists('width', $meta)) {
            $updated['width'] = $meta['width'];
        }
        if (array_key_exists('height', $updated) && array_key_exists('height', $meta)) {
            $updated['height'] = $meta['height'];
        }
        if (array_key_exists('optimized', $updated)) {
            $updated['optimized'] = true;
        }

        if ($this->deleteOriginals) {
            $this->deletePathIfExists($disk, $this->extractStoragePath(is_string($item['preview_path'] ?? null) ? $item['preview_path'] : ''), $updatedPreviewPath);
            $this->deletePathIfExists($disk, $this->extractStoragePath(is_string($item['preview_url'] ?? null) ? $item['preview_url'] : ''), $updatedPreviewPath);
        }

        return [$updated, $updated !== $item, $wasOptimized ? 1 : 0, 0];
    }

    protected function optimizePath(string $disk, string $path): array
    {
        $path = ltrim(trim($path), '/');
        if ($path === '') {
            return [null, false];
        }

        if (! $this->isOptimizableExtension($path)) {
            return [null, false];
        }

        $cacheKey = $disk . ':' . $path;
        if (array_key_exists($cacheKey, $this->cache)) {
            $cached = $this->cache[$cacheKey];

            return [$cached === false ? null : $cached, false];
        }

        $meta = $this->imageUploadService->optimizeExistingStorageImage($disk, $path);
        $this->cache[$cacheKey] = $meta ?: false;

        if ($meta) {
            $this->deletePathIfExists(
                $disk,
                $path,
                is_string($meta['path'] ?? null) ? $meta['path'] : null
            );
        }

        return [$meta, $meta !== null];
    }

    protected function deletePathIfExists(string $disk, ?string $oldPath, ?string $newPath = null): void
    {
        if (! $this->deleteOriginals || ! is_string($oldPath) || trim($oldPath) === '') {
            return;
        }

        $oldPath = ltrim(trim($oldPath), '/');
        if ($oldPath === '' || ($newPath && ltrim($newPath, '/') === $oldPath)) {
            return;
        }

        $key = $disk . ':' . $oldPath;
        if (array_key_exists($key, $this->deletedPaths)) {
            return;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($oldPath)) {
            $this->deletedPaths[$key] = true;

            return;
        }

        if ($storage->delete($oldPath)) {
            $this->deletedPaths[$key] = true;
        }
    }

    protected function iterateModels(string $target, Builder $query, callable $callback): void
    {
        $lastId = 0;
        $processed = 0;

        while (true) {
            if ($this->limitPerTarget > 0 && $processed >= $this->limitPerTarget) {
                break;
            }

            $take = $this->chunkSize;
            if ($this->limitPerTarget > 0) {
                $take = min($take, $this->limitPerTarget - $processed);
            }

            $batch = (clone $query)
                ->where('id', '>', $lastId)
                ->limit($take)
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            /** @var Model $model */
            foreach ($batch as $model) {
                try {
                    $callback($model);
                } catch (\Throwable $exception) {
                    $this->summary[$target]['errors']++;
                    $this->error(sprintf('Record #%d failed: %s', (int) $model->getKey(), $exception->getMessage()));
                }

                $lastId = max($lastId, (int) $model->getKey());
                $processed++;
            }
        }
    }

    protected function resolveTargets(): array
    {
        $allowed = ['products', 'collections', 'users', 'verifications', 'blog', 'campaigns', 'filesystem'];
        $requested = collect($this->option('only'))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->values()
            ->all();

        if ($requested === []) {
            return $allowed;
        }

        return collect($requested)
            ->filter(fn (string $target) => in_array($target, $allowed, true))
            ->values()
            ->all();
    }

    protected function resolveDiskFromPayload(array $payload): string
    {
        foreach (['disk', 'storage_disk'] as $key) {
            if (! is_string($payload[$key] ?? null)) {
                continue;
            }

            $value = trim($payload[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return $this->defaultDisk;
    }

    protected function resolvePathFromPayload(array $payload): ?string
    {
        foreach (['path', 'storage_path', 'url', 'thumb_url', 'preview_url', 'preview_path'] as $key) {
            if (! is_string($payload[$key] ?? null)) {
                continue;
            }

            $path = $this->extractStoragePath($payload[$key]);
            if ($path) {
                return $path;
            }
        }

        return null;
    }

    protected function extractStoragePath(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $value = trim((string) parse_url($value, PHP_URL_PATH));
        }

        if ($value === '') {
            return null;
        }

        if (Str::contains($value, '/storage/')) {
            $value = Str::after($value, '/storage/');
        } elseif (Str::startsWith($value, '/storage/')) {
            $value = Str::after($value, '/storage/');
        } elseif (Str::startsWith($value, 'storage/')) {
            $value = Str::after($value, 'storage/');
        }

        $value = ltrim($value, '/');
        if ($value === '') {
            return null;
        }

        return $value;
    }

    protected function looksLikeUrl(string $value): bool
    {
        $value = trim($value);

        return Str::startsWith($value, ['http://', 'https://', '/storage/']);
    }

    protected function isOptimizableExtension(string $path): bool
    {
        $extension = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png'], true);
    }

    protected function blankStats(): array
    {
        return [
            'scanned' => 0,
            'updated' => 0,
            'optimized' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }

    protected function outputSummaryTable(): void
    {
        $rows = collect($this->summary)
            ->map(fn (array $stats, string $target) => [
                'target' => $target,
                'scanned' => $stats['scanned'],
                'updated' => $stats['updated'],
                'optimized' => $stats['optimized'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
            ])
            ->values()
            ->all();

        $this->info('Optimization summary');
        $this->table(
            ['Target', 'Scanned', 'Updated', 'Optimized files', 'Skipped', 'Errors'],
            $rows
        );
    }
}
