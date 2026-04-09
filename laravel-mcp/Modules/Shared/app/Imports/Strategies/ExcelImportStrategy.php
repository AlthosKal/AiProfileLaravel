<?php

namespace Modules\Shared\Imports\Strategies;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Shared\Imports\Interfaces\ImportInterface;

/**
 * Estrategia concreta de importación desde archivos Excel (.xlsx).
 *
 * Recibe el Sheet del módulo correspondiente vía constructor, manteniéndose
 * agnóstica del dominio. Cualquier módulo puede reutilizarla pasando
 * su propio Sheet sin modificar esta clase.
 */
readonly class ExcelImportStrategy implements ImportInterface
{
    /**
     * @param  object  $sheet  Clase Sheet del módulo que implementa los concerns de Maatwebsite.
     */
    public function __construct(private object $sheet) {}

    public function import(UploadedFile $file): void
    {
        Excel::import($this->sheet, $file, null, ExcelType::XLSX);
    }
}
