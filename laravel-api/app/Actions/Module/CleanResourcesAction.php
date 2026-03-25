<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Support\Facades\File;

class CleanResourcesAction
{
    use HasConsoleOutput;

    public function handle(string $modulePath): void
    {
        $this->info('🗑️  Limpiando directorio resources/...');

        $resourcesPath = $modulePath.'/resources';

        if (! File::isDirectory($resourcesPath)) {
            $this->warn('   ⚠ Directorio resources/ no encontrado');

            return;
        }

        File::cleanDirectory($resourcesPath);

        $this->info('   ✓ resources/ limpiado');
    }
}
