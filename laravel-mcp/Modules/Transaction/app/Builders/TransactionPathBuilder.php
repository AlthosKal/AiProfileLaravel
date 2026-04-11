<?php

namespace Modules\Transaction\Builders;

use Illuminate\Http\UploadedFile;
use Modules\Shared\Builders\ObjectPathBuilder;

class TransactionPathBuilder implements ObjectPathBuilder
{
    /**
     * Build a safe, versioned storage path for a transaction import file.
     *
     * The filename is content-hashed to:
     * - avoid collisions
     * - prevent path traversal (never trusts the client-supplied name)
     * - enable cache-busting when content changes
     *
     * Extension is resolved from the MIME type, never from the client name.
     */
    public static function build(string $filename): string
    {
        return 'transactions/imports/'.$filename;
    }

    /**
     * Build a path from the actual UploadedFile instance.
     * Prefer this over build() when you have the file available.
     */
    public static function buildFromFile(UploadedFile $file): string
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $extension = $file->extension(); // resolved from MIME type, not client name

        return 'transactions/imports/'.$hash.'.'.$extension;
    }

    /**
     * Build a deterministic storage path for a transaction export file.
     *
     * @param  string  $filename  Base filename without extension (e.g. "transacciones-2026-01-01-2026-01-31").
     * @param  string  $extension  File extension resolved from the export format (e.g. "xlsx", "csv").
     */
    public static function buildForExport(string $filename, string $extension): string
    {
        return 'transactions/exports/'.$filename.'.'.$extension;
    }
}
