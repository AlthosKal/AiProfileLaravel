<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Enums\ExportFormat;
use Modules\Transaction\Exports\Sheets\TransactionExportSheet;
use Modules\Transaction\Exports\TransactionExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Genera y descarga un archivo con las transacciones del sistema
 * filtradas por rango de fechas en el formato solicitado (Excel, CSV, etc.).
 *
 * El nombre del archivo incluye el rango para facilitar su identificación.
 */
readonly class ExportTransactionAction
{
    public function export(ExportFormat $format, string $dateFrom, string $dateTo): BinaryFileResponse
    {
        $sheet = new TransactionExportSheet($dateFrom, $dateTo);
        $strategy = $format->resolveExportStrategy($sheet);
        $exporter = new TransactionExporter($strategy);

        return $exporter->export("transacciones-$dateFrom-$dateTo");
    }
}
