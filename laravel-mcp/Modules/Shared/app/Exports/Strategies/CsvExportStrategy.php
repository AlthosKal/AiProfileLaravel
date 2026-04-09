<?php

namespace Modules\Shared\Exports\Strategies;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Modules\Shared\Exports\Interfaces\ExportInterface;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Estrategia concreta de exportación en formato CSV.
 *
 * Recibe el Sheet del módulo correspondiente vía constructor, manteniéndose
 * agnóstica del dominio. Cualquier módulo puede reutilizarla pasando
 * su propio Sheet sin modificar esta clase.
 */
readonly class CsvExportStrategy implements ExportInterface
{
    /**
     * @param  object  $sheet  Clase Sheet del módulo que implementa los concerns de Maatwebsite.
     */
    public function __construct(private object $sheet) {}

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function export(string $filename): BinaryFileResponse
    {
        return ExcelFacade::download($this->sheet, "$filename.csv", Excel::CSV);
    }
}
