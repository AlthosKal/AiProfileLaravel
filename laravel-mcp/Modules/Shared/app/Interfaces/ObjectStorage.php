<?php

namespace Modules\Shared\Interfaces;

use Illuminate\Http\UploadedFile;

interface ObjectStorage
{
    /**
     * Store the file at the given path on the configured disk.
     *
     * Returns the full path where the file was stored.
     */
    public static function store(string $path, UploadedFile $file): string;

    /**
     * Generate a temporary pre-signed URL for a private file.
     *
     * @param  int  $minutes  Number of minutes until the URL expires.
     */
    public static function temporaryUrl(string $path, int $minutes = 5): string;
}
