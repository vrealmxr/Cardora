<?php

namespace App\Services;

/** Reads headers of existing public listing images only. Never fetches a URL or decodes pixels. */
class PublicMediaDimensions
{
    private array $memo = [];

    public function __construct(private string $listingDirectory)
    {
    }

    public function forUrl(?string $url): array
    {
        if (!$url) return [];
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) return [];
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return [];
        if (isset($parts['host']) && !in_array(strtolower($parts['host']), ['cardora.gr', 'www.cardora.gr', 'api.cardora.gr'], true)) return [];
        $path = rawurldecode($parts['path'] ?? '');
        $prefix = '/storage/uploads/listings/';
        if (!str_starts_with($path, $prefix) || preg_match('/[\\\\\x00-\x1f%]/', $path)) return [];
        $relative = substr($path, strlen($prefix));
        if (array_intersect(explode('/', $relative), ['.', '..', ''])) return [];
        if (!in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif'], true)) return [];
        $root = realpath($this->listingDirectory);
        if ($root === false) return [];
        $file = realpath($root.DIRECTORY_SEPARATOR.$relative);
        if ($file === false || !str_starts_with($file, $root.DIRECTORY_SEPARATOR) || !is_file($file)) return [];
        if (array_key_exists($file, $this->memo)) return $this->memo[$file];
        $this->memo[$file] = [];
        $size = @filesize($file);
        if ($size === false || $size < 1 || $size > 25 * 1024 * 1024) return [];
        $dimensions = @getimagesize($file);
        if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 50000 || $dimensions[1] > 50000) return [];
        return $this->memo[$file] = ['width' => $dimensions[0], 'height' => $dimensions[1]];
    }
}
