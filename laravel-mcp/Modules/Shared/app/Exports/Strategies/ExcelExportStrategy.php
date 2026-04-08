<?php

namespace Modules\Shared\Exports\Strategies;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Modules\Shared\Exports\Interfaces\ExportInterface;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

readonly class ExcelExportStrategy implements ExportInterface
{
    public function __construct(private object $sheet) {}

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function export(string $filename): BinaryFileResponse
    {
        return ExcelFacade::download($this->sheet, "$filename.xlsx", Excel::XLSX);
    }
}
