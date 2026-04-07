<?php

namespace Modules\Shared\Enums;

use Modules\Shared\Exports\Interfaces\ExportInterface;
use Modules\Shared\Exports\Strategies\CsvExportStrategy;
use Modules\Shared\Exports\Strategies\ExcelExportStrategy;
use Modules\Shared\Imports\Interfaces\ImportInterface;
use Modules\Shared\Imports\Strategies\CsvImportStrategy;
use Modules\Shared\Imports\Strategies\ExcelImportStrategy;

enum ExportFormat: string
{
    case Excel = 'excel';
    case Csv = 'csv';

    public function resolveExportStrategy(object $sheet): ExportInterface
    {
        return match ($this) {
            self::Excel => new ExcelExportStrategy($sheet),
            self::Csv => new CsvExportStrategy($sheet),
        };
    }

    public function resolveImportStrategy(object $sheet): ImportInterface
    {
        return match ($this) {
            self::Excel => new ExcelImportStrategy($sheet),
            self::Csv => new CsvImportStrategy($sheet),
        };
    }
}
