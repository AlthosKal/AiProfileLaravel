<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Enums\ExportFormat;
use Modules\Shared\Enums\StorageType;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Builders\TransactionPathBuilder;
use Modules\Transaction\Enums\FileType;
use Modules\Transaction\Exports\Sheets\TransactionExportSheet;
use Modules\Transaction\Exports\TransactionExporter;
use Modules\Transaction\Models\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Genera y descarga un archivo con las transacciones del sistema
 * filtradas por rango de fechas en el formato solicitado (Excel, CSV, etc.).
 *
 * El nombre del archivo incluye el rango para facilitar su identificación.
 * Una copia del archivo también se almacena en MinIO (S3) para su posterior descarga.
 */
readonly class ExportTransactionAction
{
    public function export(ExportFormat $format, string $dateFrom, string $dateTo, GatewayUser $user): BinaryFileResponse
    {
        $filename = "transacciones-$dateFrom-$dateTo";

        $sheet = new TransactionExportSheet($dateFrom, $dateTo);
        $strategy = $format->resolveExportStrategy($sheet);
        $exporter = new TransactionExporter($strategy);

        $path = TransactionPathBuilder::buildForExport($filename, $format->extension());

        File::create([
            'user_email' => $user->email,
            'name' => "$filename.{$format->extension()}",
            'path' => $path,
            'type' => FileType::EXPORT,
        ]);

        $exporter->store($path, StorageType::DISK->value);

        return $exporter->export($filename);
    }
}
