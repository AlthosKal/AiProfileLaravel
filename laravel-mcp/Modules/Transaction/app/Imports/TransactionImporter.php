<?php

namespace Modules\Transaction\Imports;

use Illuminate\Http\UploadedFile;
use Modules\Shared\Imports\Interfaces\ImportInterface;

readonly class TransactionImporter
{
    public function __construct(private ImportInterface $strategy) {}

    public function import(UploadedFile $file): void
    {
        $this->strategy->import($file);
    }
}
