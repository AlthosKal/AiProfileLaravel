<?php

namespace Modules\Shared\Exports\Interfaces;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface ExportInterface
{
    public function export(string $filename): BinaryFileResponse;
}
