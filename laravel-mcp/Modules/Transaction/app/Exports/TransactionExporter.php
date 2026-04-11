<?php

namespace Modules\Transaction\Exports;

use Modules\Shared\Exports\Interfaces\ExportInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Context del patrón Strategy para exportación de transacciones.
 *
 * Orquesta la exportación delegando el renderizado al strategy concreto
 * inyectado. No conoce el formato de salida, solo invoca el contrato.
 */
readonly class TransactionExporter
{
    /**
     * @param  ExportInterface  $strategy  Estrategia de formato resuelta por ExportFormat.
     */
    public function __construct(private ExportInterface $strategy) {}

    public function export(string $filename): BinaryFileResponse
    {
        return $this->strategy->export($filename);
    }

    public function store(string $path, string $disk): void
    {
        $this->strategy->store($path, $disk);
    }
}
