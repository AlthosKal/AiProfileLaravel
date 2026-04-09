<?php

namespace Modules\Shared\Stores;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Shared\Enums\StorageType;
use Modules\Shared\Interfaces\ObjectStorage;

class CloudObjectStorage implements ObjectStorage
{
    /**
     * Store the file using streaming to avoid loading it entirely into memory.
     * The file is stored as private; access must go through a signed URL.
     */
    public static function store(string $path, UploadedFile $file): string
    {
        return Storage::disk(StorageType::DISK->value)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            'private',
        );
    }

    /**
     * Store a file from a local filesystem path (e.g. sandbox-generated files).
     * The file is stored as private; access must go through a signed URL.
     */
    public static function storeFromPath(string $storagePath, string $localPath): string
    {
        $stream = fopen($localPath, 'r');

        Storage::disk(StorageType::DISK->value)->put($storagePath, $stream, 'private');

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $storagePath;
    }

    /**
     * Generate a temporary pre-signed URL for a private file.
     */
    public static function temporaryUrl(string $path, int $minutes = 5): string
    {
        return Storage::disk(StorageType::DISK->value)->temporaryUrl($path, now()->addMinutes($minutes));
    }
}
