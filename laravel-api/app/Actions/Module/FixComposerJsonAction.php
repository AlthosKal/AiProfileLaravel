<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class FixComposerJsonAction
{
    use HasConsoleOutput;

    /**
     * Cambio: "Modules\\ModuleName\\": "App/" → "app/"
     *
     * @throws FileNotFoundException
     */
    public function handle(string $modulePath, string $moduleName): void
    {
        $this->info('🔧 Corrigiendo composer.json...');

        $composerPath = $modulePath.'/composer.json';

        if (! File::exists($composerPath)) {
            $this->warn('   ⚠ composer.json no encontrado');

            return;
        }

        $content = File::get($composerPath);
        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('   ✗ Error al decodificar composer.json');

            return;
        }

        if (isset($json['autoload']['psr-4']["Modules\\$moduleName\\"])) {
            $json['autoload']['psr-4']["Modules\\$moduleName\\"] = 'app/';
        }

        File::put($composerPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info('   ✓ composer.json corregido');
    }
}
