<?php

namespace Modules\Shared\Enums;

use Modules\Shared\Exports\Interfaces\ExportInterface;
use Modules\Shared\Exports\Strategies\CsvExportStrategy;
use Modules\Shared\Exports\Strategies\ExcelExportStrategy;
use Modules\Shared\Imports\Interfaces\ImportInterface;
use Modules\Shared\Imports\Strategies\CsvImportStrategy;
use Modules\Shared\Imports\Strategies\ExcelImportStrategy;

/**
 * Formatos de archivo soportados para importación y exportación.
 *
 * Actúa como punto único de registro de formatos válidos y como fábrica
 * de las estrategias concretas. Para agregar un nuevo formato basta con:
 *   1. Añadir un nuevo case a este enum.
 *   2. Crear las clases Strategy correspondientes en Shared.
 *   3. Mapearlas en los métodos resolve* — sin tocar ningún módulo de dominio.
 */
enum ExportFormat: string
{
    case Excel = 'excel';
    case Csv = 'csv';

    /**
     * Resuelve la estrategia de exportación según el formato seleccionado.
     *
     * @param  object  $sheet  Sheet del módulo con los datos y estructura a exportar.
     */
    public function resolveExportStrategy(object $sheet): ExportInterface
    {
        return match ($this) {
            self::Excel => new ExcelExportStrategy($sheet),
            self::Csv => new CsvExportStrategy($sheet),
        };
    }

    /**
     * Resuelve la estrategia de importación según el formato seleccionado.
     *
     * @param  object  $sheet  Sheet del módulo con el mapeo y validación de filas.
     */
    public function resolveImportStrategy(object $sheet): ImportInterface
    {
        return match ($this) {
            self::Excel => new ExcelImportStrategy($sheet),
            self::Csv => new CsvImportStrategy($sheet),
        };
    }
}
