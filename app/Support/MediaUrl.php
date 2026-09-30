<?php

namespace App\Support;

class MediaUrl
{
    /**
     * Public URL for a file on the public disk, served through MediaController (which adds CORS headers).
     * Accepts paths stored with or without a leading "storage/".
     */
    public static function for(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return route('media.show', ['path' => $path]);
    }
}
