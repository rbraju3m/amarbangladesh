<?php

namespace App\Support;

final class PublicUrl
{
    /**
     * Root-relative URL for a file in public/ ("images/x.svg" → "/images/x.svg").
     * Used for anything that gets cached, so the host from APP_URL is never baked in.
     * Values that are already absolute URLs are returned unchanged.
     */
    public static function path(string $path): string
    {
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        return '/'.ltrim($path, '/');
    }
}
