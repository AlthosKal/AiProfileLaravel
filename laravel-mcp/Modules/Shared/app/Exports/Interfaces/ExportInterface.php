<?php

namespace Modules\Shared\Exports\Interfaces;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Contrato del patrón Strategy para exportación de datos.
 *
 * Cada implementación concreta define cómo se renderiza y descarga
 * el archivo (Excel, CSV, etc.) sin que el código llamante necesite
 * conocer el formato específico.
 */
interface ExportInterface
{
    /**
     * Genera y descarga el archivo en el formato concreto de la estrategia.
     *
     * @param  string  $filename  Nombre base del archivo sin extensión.
     */
    public function export(string $filename): BinaryFileResponse;

    /**
     * Almacena el archivo generado en el disco indicado (e.g. S3/MinIO).
     *
     * @param  string  $path  Path completo de destino dentro del disco.
     * @param  string  $disk  Nombre del disco de Laravel (e.g. 's3', 'local').
     */
    public function store(string $path, string $disk): void;
}
