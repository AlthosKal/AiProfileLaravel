<?php

namespace Modules\Transaction\Exports;

use Modules\Shared\Exports\Interfaces\ExportInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

readonly class TransactionExporter
{
    public function __construct(private ExportInterface $strategy) {}

    public function export(string $filename): BinaryFileResponse
    {
        return $this->strategy->export($filename);
    }
}
