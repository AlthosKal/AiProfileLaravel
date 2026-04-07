<?php

namespace Modules\Shared\Imports\Strategies;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Shared\Imports\Interfaces\ImportInterface;

readonly class ExcelImportStrategy implements ImportInterface
{
    public function __construct(private object $sheet) {}

    public function import(UploadedFile $file): void
    {
        Excel::import($this->sheet, $file, null, ExcelType::XLSX);
    }
}
