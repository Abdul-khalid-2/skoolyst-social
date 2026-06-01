<?php

namespace App\Support;

final class AvatarUrl
{
    private const MAX_LENGTH = 2048;

    /**
     * Normalize an OAuth or API avatar URL for database storage.
     */
    public static function forStorage(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Meta CDN URLs are valid without long tracking query strings.
        if (str_contains($url, 'fbcdn.net') || str_contains($url, 'cdninstagram.com')) {
            $base = strtok($url, '?');
            if (is_string($base) && $base !== '') {
                $url = $base;
            }
        }

        if (strlen($url) > self::MAX_LENGTH) {
            return null;
        }

        return $url;
    }
}
