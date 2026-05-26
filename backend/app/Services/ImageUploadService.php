<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    protected const OPTIMIZABLE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    public function store(UploadedFile $file, string $directory, string $disk): array
    {
        if (! $this->canOptimize($file)) {
            return $this->storeOriginalFile($file, $directory, $disk);
        }

        $optimizedFile = $this->storeOptimizedImage($file, $directory, $disk);

        if ($optimizedFile !== null) {
            return $optimizedFile;
        }

        return $this->storeOriginalFile($file, $directory, $disk);
    }

    public function optimizeExistingStorageImage(
        string $disk,
        string $path,
        array $options = []
    ): ?array {
        $path = ltrim(trim($path), '/');
        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $storageDisk = Storage::disk($disk);
        $absolutePath = method_exists($storageDisk, 'path')
            ? $storageDisk->path($path)
            : null;

        if (! is_string($absolutePath) || $absolutePath === '' || ! is_file($absolutePath)) {
            return null;
        }

        $mimeType = Str::lower((string) (mime_content_type($absolutePath) ?: ''));
        if ($mimeType === '' || ! in_array($mimeType, self::OPTIMIZABLE_MIME_TYPES, true)) {
            return null;
        }

        if (! $this->hasOptimizationCapabilities()) {
            return null;
        }

        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $originalExtension = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));
        $targetPath = $originalExtension === 'webp'
            ? $path
            : sprintf('%s/%s.webp', $directory === '.' ? '' : $directory, $filename);
        $targetPath = ltrim($targetPath, '/');

        $quality = (int) ($options['quality'] ?? config('uploads.image_optimization.quality', 80));
        $quality = max(0, min(100, $quality));
        $generatePreview = (bool) ($options['generate_preview'] ?? config('uploads.image_optimization.generate_preview', true));
        $previewQuality = (int) ($options['preview_quality'] ?? config('uploads.image_optimization.preview_quality', 72));
        $previewQuality = max(0, min(100, $previewQuality));
        $maxWidth = (int) ($options['max_width'] ?? config('uploads.image_optimization.max_width', 1920));
        $maxHeight = (int) ($options['max_height'] ?? config('uploads.image_optimization.max_height', 1920));
        $previewMaxWidth = (int) ($options['preview_max_width'] ?? config('uploads.image_optimization.preview_max_width', 640));
        $previewMaxHeight = (int) ($options['preview_max_height'] ?? config('uploads.image_optimization.preview_max_height', 640));

        [$mainMeta, $previewMeta] = $this->optimizeFromAbsolutePath(
            sourcePath: $absolutePath,
            sourceMimeType: $mimeType,
            disk: $disk,
            targetPath: $targetPath,
            quality: $quality,
            maxWidth: $maxWidth,
            maxHeight: $maxHeight,
            generatePreview: $generatePreview,
            previewMaxWidth: $previewMaxWidth,
            previewMaxHeight: $previewMaxHeight,
            previewQuality: $previewQuality
        );

        if ($mainMeta === null) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $mainMeta['path'],
            'preview_path' => $previewMeta['path'] ?? null,
            'mime_type' => 'image/webp',
            'original_mime_type' => $mimeType,
            'file_size' => $mainMeta['size'],
            'url' => $storageDisk->url($mainMeta['path']),
            'preview_url' => isset($previewMeta['path']) ? $storageDisk->url($previewMeta['path']) : null,
            'width' => $mainMeta['width'],
            'height' => $mainMeta['height'],
            'optimized' => true,
            'target_changed' => $mainMeta['path'] !== $path,
        ];
    }

    protected function canOptimize(UploadedFile $file): bool
    {
        if (! (bool) config('uploads.image_optimization.enabled', true)) {
            return false;
        }

        $mimeType = Str::lower((string) $file->getMimeType());
        if (! in_array($mimeType, self::OPTIMIZABLE_MIME_TYPES, true)) {
            return false;
        }

        return $this->hasOptimizationCapabilities();
    }

    protected function hasOptimizationCapabilities(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagewebp');
    }

    protected function storeOptimizedImage(UploadedFile $file, string $directory, string $disk): ?array
    {
        $sourcePath = $file->getRealPath();
        if (! is_string($sourcePath) || $sourcePath === '' || ! is_file($sourcePath)) {
            return null;
        }

        $fileName = Str::uuid()->toString();
        $mainPath = sprintf('%s/%s.webp', trim($directory, '/'), $fileName);

        [$mainMeta, $previewMeta] = $this->optimizeFromAbsolutePath(
            sourcePath: $sourcePath,
            sourceMimeType: Str::lower((string) $file->getMimeType()),
            disk: $disk,
            targetPath: ltrim($mainPath, '/'),
            quality: (int) config('uploads.image_optimization.quality', 80),
            maxWidth: (int) config('uploads.image_optimization.max_width', 1920),
            maxHeight: (int) config('uploads.image_optimization.max_height', 1920),
            generatePreview: (bool) config('uploads.image_optimization.generate_preview', true),
            previewMaxWidth: (int) config('uploads.image_optimization.preview_max_width', 640),
            previewMaxHeight: (int) config('uploads.image_optimization.preview_max_height', 640),
            previewQuality: (int) config('uploads.image_optimization.preview_quality', 72),
            exifSourceFile: $sourcePath,
        );

        if ($mainMeta === null) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $mainMeta['path'],
            'preview_path' => $previewMeta['path'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => 'image/webp',
            'original_mime_type' => $file->getClientMimeType(),
            'file_size' => $mainMeta['size'],
            'url' => Storage::disk($disk)->url($mainMeta['path']),
            'preview_url' => isset($previewMeta['path']) ? Storage::disk($disk)->url($previewMeta['path']) : null,
            'width' => $mainMeta['width'],
            'height' => $mainMeta['height'],
            'uploaded_at' => now()->toIso8601String(),
            'optimized' => true,
        ];
    }

    protected function optimizeFromAbsolutePath(
        string $sourcePath,
        string $sourceMimeType,
        string $disk,
        string $targetPath,
        int $quality,
        int $maxWidth,
        int $maxHeight,
        bool $generatePreview,
        int $previewMaxWidth,
        int $previewMaxHeight,
        int $previewQuality,
        ?string $exifSourceFile = null
    ): array {
        $imageInfo = @getimagesize($sourcePath);
        if (! is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return [null, null];
        }

        $sourceWidth = (int) $imageInfo[0];
        $sourceHeight = (int) $imageInfo[1];
        $maxPixels = (int) config('uploads.image_optimization.max_pixels', 40000000);
        if ($sourceWidth <= 0 || $sourceHeight <= 0 || ($sourceWidth * $sourceHeight) > $maxPixels) {
            return [null, null];
        }

        $sourceImage = $this->createImageResource($sourcePath, $sourceMimeType);
        if (! $sourceImage) {
            return [null, null];
        }

        try {
            $sourceImage = $this->applyExifOrientationIfNeeded(
                $sourceImage,
                $sourceMimeType,
                $exifSourceFile ?? $sourcePath
            );

            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);

            [$mainWidth, $mainHeight] = $this->fitWithin($sourceWidth, $sourceHeight, $maxWidth, $maxHeight);
            $mainImage = $this->resizeImage($sourceImage, $sourceWidth, $sourceHeight, $mainWidth, $mainHeight);
            if (! $mainImage) {
                return [null, null];
            }

            $quality = max(0, min(100, $quality));
            if (! $this->saveImageResourceToDisk($mainImage, $disk, $targetPath, $quality)) {
                imagedestroy($mainImage);
                return [null, null];
            }
            imagedestroy($mainImage);

            $mainSize = Storage::disk($disk)->size($targetPath);
            $mainMeta = [
                'path' => $targetPath,
                'width' => $mainWidth,
                'height' => $mainHeight,
                'size' => is_int($mainSize) ? $mainSize : null,
            ];

            $previewMeta = null;
            if ($generatePreview) {
                [$previewWidth, $previewHeight] = $this->fitWithin(
                    $sourceWidth,
                    $sourceHeight,
                    $previewMaxWidth,
                    $previewMaxHeight
                );
                $previewImage = $this->resizeImage(
                    $sourceImage,
                    $sourceWidth,
                    $sourceHeight,
                    $previewWidth,
                    $previewHeight
                );

                if ($previewImage) {
                    $previewPath = preg_replace('/\.webp$/i', '-preview.webp', $targetPath) ?: ($targetPath . '-preview.webp');
                    $previewQuality = max(0, min(100, $previewQuality));

                    if ($this->saveImageResourceToDisk($previewImage, $disk, $previewPath, $previewQuality)) {
                        $previewSize = Storage::disk($disk)->size($previewPath);
                        $previewMeta = [
                            'path' => $previewPath,
                            'width' => $previewWidth,
                            'height' => $previewHeight,
                            'size' => is_int($previewSize) ? $previewSize : null,
                        ];
                    }

                    imagedestroy($previewImage);
                }
            }

            return [$mainMeta, $previewMeta];
        } finally {
            if (is_object($sourceImage) || is_resource($sourceImage)) {
                imagedestroy($sourceImage);
            }
        }
    }

    protected function createImageResource(string $sourcePath, string $mimeType)
    {
        return match (Str::lower($mimeType)) {
            'image/jpeg', 'image/jpg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
    }

    protected function applyExifOrientationIfNeeded($imageResource, string $mimeType, string $sourcePath)
    {
        if ($mimeType !== 'image/jpeg' && $mimeType !== 'image/jpg') {
            return $imageResource;
        }

        if (! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return $imageResource;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => @imagerotate($imageResource, 180, 0),
            6 => @imagerotate($imageResource, -90, 0),
            8 => @imagerotate($imageResource, 90, 0),
            default => false,
        };

        if (! $rotated) {
            return $imageResource;
        }

        imagedestroy($imageResource);
        return $rotated;
    }

    protected function resizeImage($sourceImage, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight)
    {
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $targetImage) {
            return false;
        }

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);

        $resampled = imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        if (! $resampled) {
            imagedestroy($targetImage);
            return false;
        }

        return $targetImage;
    }

    protected function saveImageResourceToDisk($imageResource, string $disk, string $path, int $quality): bool
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'cardora-webp-');
        if (! is_string($temporaryFile) || $temporaryFile === '') {
            return false;
        }

        try {
            if (! @imagewebp($imageResource, $temporaryFile, $quality)) {
                return false;
            }

            $contents = @file_get_contents($temporaryFile);
            if ($contents === false) {
                return false;
            }

            return Storage::disk($disk)->put($path, $contents, ['visibility' => 'public']);
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    protected function storeOriginalFile(UploadedFile $file, string $directory, string $disk): array
    {
        $path = $file->store($directory, $disk);
        $size = Storage::disk($disk)->size($path);

        return [
            'disk' => $disk,
            'path' => $path,
            'preview_path' => null,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'original_mime_type' => $file->getClientMimeType(),
            'file_size' => is_int($size) ? $size : $file->getSize(),
            'url' => Storage::disk($disk)->url($path),
            'preview_url' => null,
            'uploaded_at' => now()->toIso8601String(),
            'optimized' => false,
        ];
    }

    protected function fitWithin(int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): array
    {
        $maxWidth = max(1, $maxWidth);
        $maxHeight = max(1, $maxHeight);

        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);

        $targetWidth = max(1, (int) round($sourceWidth * $ratio));
        $targetHeight = max(1, (int) round($sourceHeight * $ratio));

        return [$targetWidth, $targetHeight];
    }
}
