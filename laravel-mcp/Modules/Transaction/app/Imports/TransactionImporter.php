<?php

namespace Modules\Transaction\Imports;

use Illuminate\Http\UploadedFile;
use Modules\Shared\Imports\Interfaces\ImportInterface;

/**
 * Context del patrón Strategy para importación de transacciones.
 *
 * Orquesta la importación delegando el procesamiento del archivo al strategy
 * concreto inyectado. No conoce el formato de entrada, solo invoca el contrato.
 */
readonly class TransactionImporter
{
    /**
     * @param  ImportInterface  $strategy  Estrategia de formato resuelta por ExportFormat.
     */
    public function __construct(private ImportInterface $strategy) {}

    public function import(UploadedFile $file): void
    {
        $this->strategy->import($file);
    }
}
