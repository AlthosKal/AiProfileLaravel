<?php

namespace Modules\Shared\Exports\Strategies;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Modules\Shared\Exports\Interfaces\ExportInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportStrategy implements ExportInterface
{
    public function __construct(private readonly object $sheet) {}

    public function export(string $filename): BinaryFileResponse
    {
        return ExcelFacade::download($this->sheet, "$filename.xlsx", Excel::XLSX);
    }
}
