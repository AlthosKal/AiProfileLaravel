<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Support\Facades\File;

class RemoveFrontendFilesAction
{
    use HasConsoleOutput;

    public function handle(string $modulePath): void
    {
        $this->info('🗑️  Eliminando archivos frontend...');

        $files = [
            $modulePath.'/vite.config.js',
            $modulePath.'/package.json',
        ];

        foreach ($files as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->info('   ✓ '.basename($file).' eliminado');
            }
        }
    }
}
