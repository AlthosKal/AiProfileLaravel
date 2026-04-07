<?php

namespace Modules\Shared\Imports\Interfaces;

use Illuminate\Http\UploadedFile;

interface ImportInterface
{
    public function import(UploadedFile $file): void;
}
