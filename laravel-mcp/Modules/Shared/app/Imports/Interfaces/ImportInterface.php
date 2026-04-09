<?php

namespace Modules\Shared\Imports\Interfaces;

use Illuminate\Http\UploadedFile;

/**
 * Contrato del patrón Strategy para importación de datos.
 *
 * Cada implementación concreta define cómo se lee y procesa
 * el archivo subido (Excel, CSV, etc.) sin que el código llamante
 * necesite conocer el formato específico.
 */
interface ImportInterface
{
    /**
     * Procesa el archivo subido usando la estrategia concreta.
     *
     * @param  UploadedFile  $file  Archivo enviado via multipart/form-data.
     */
    public function import(UploadedFile $file): void;
}
