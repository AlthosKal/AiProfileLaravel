<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Enums\ExportFormat;
use Modules\Transaction\Exports\Sheets\TransactionExportSheet;
use Modules\Transaction\Exports\TransactionExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

readonly class ExportTransactionAction
{
    public function export(ExportFormat $format): BinaryFileResponse
    {
        $sheet = new TransactionExportSheet;
        $strategy = $format->resolveExportStrategy($sheet);
        $exporter = new TransactionExporter($strategy);

        return $exporter->export('transacciones-'.now()->format('Y-m-d'));
    }
}
