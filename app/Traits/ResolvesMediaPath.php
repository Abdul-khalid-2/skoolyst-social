<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait ResolvesMediaPath
{
    /**
     * Convert a stored public URL (any domain) to an absolute filesystem path.
     *
     * Two URL patterns are supported, matching the two disks in filesystems.php:
     *
     *   /storage/...  → storage_path('app/public/...')   (the 'public' disk)
     *   /media/...    → public_path('media/...')          (the 'website' disk, root = public_path())
     *   /anything/... → public_path('anything/...')       (any other 'website' disk path)
     *
     * The domain is intentionally ignored so this works across all environments
     * (ngrok, local, staging, production) without APP_URL having to match the
     * URL that was stored at upload time.
     *
     * Returns the resolved absolute path regardless of whether the file exists.
     * Returns null only if the URL path component cannot be parsed.
     * Callers must check file_exists() / is_file() on the returned path.
     */
    private function resolveMediaPath(string $mediaUrl): ?string
    {
        $urlPath = parse_url($mediaUrl, PHP_URL_PATH);
        if (! is_string($urlPath) || $urlPath === '') {
            Log::warning('resolveMediaPath: could not parse URL path', ['mediaUrl' => $mediaUrl]);

            return null;
        }

        $relativePath = ltrim($urlPath, '/');

        // Pattern 1: URL path starts with storage/ → 'public' disk
        // e.g. /storage/workspaces/1/posts/2/file.jpg → storage_path('app/public/workspaces/...')
        if (str_starts_with($relativePath, 'storage/')) {
            $rel = substr($relativePath, strlen('storage/'));
            $abs = storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $rel));
            Log::info('resolveMediaPath: storage/ pattern', [
                'mediaUrl' => $mediaUrl,
                'rel'      => $rel,
                'abs'      => $abs,
                'exists'   => file_exists($abs),
            ]);

            return $abs;
        }

        // Pattern 2: URL path starts with media/ or any other segment → 'website' disk (root = public_path())
        // e.g. /media/skoolyst-workspace-ws-9/file.jpg → public_path('media/skoolyst-workspace-ws-9/file.jpg')
        $abs = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        Log::info('resolveMediaPath: public_path() pattern', [
            'mediaUrl' => $mediaUrl,
            'rel'      => $relativePath,
            'abs'      => $abs,
            'exists'   => file_exists($abs),
        ]);

        return $abs;
    }
}
