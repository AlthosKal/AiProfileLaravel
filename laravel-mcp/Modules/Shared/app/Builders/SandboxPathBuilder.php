<?php

namespace Modules\Shared\Builders;

/**
 * Construye rutas de almacenamiento para archivos generados por el sandbox.
 *
 * El path incluye el jobId para garantizar unicidad y facilitar la limpieza
 * de archivos temporales por job en el futuro.
 */
class SandboxPathBuilder implements ObjectPathBuilder
{
    public static function build(string $filename): string
    {
        return 'sandbox/generated/'.$filename;
    }

    /**
     * Construye un path estable a partir del jobId y el nombre del archivo de output.
     *
     * No usa el nombre provisto por el cliente; el nombre viene del script generado
     * por la IA, que es controlado por el servidor, no por el usuario final.
     */
    public static function buildForJob(string $jobId, string $outputFilename): string
    {
        return 'sandbox/generated/'.$jobId.'/'.$outputFilename;
    }
}
