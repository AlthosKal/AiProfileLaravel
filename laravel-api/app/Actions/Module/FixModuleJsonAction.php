<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class FixModuleJsonAction
{
    use HasConsoleOutput;

    /**
     * Cambio en providers: "Modules\\ModuleName\\App\\Providers\\..." → "Modules\\ModuleName\\Providers\\..."
     *
     * @throws FileNotFoundException
     */
    public function handle(string $modulePath, string $moduleName): void
    {
        $this->info('🔧 Corrigiendo module.json...');

        $moduleJsonPath = $modulePath.'/module.json';

        if (! File::exists($moduleJsonPath)) {
            $this->warn('   ⚠ module.json no encontrado');

            return;
        }

        $content = File::get($moduleJsonPath);
        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('   ✗ Error al decodificar module.json');

            return;
        }

        if (isset($json['providers']) && is_array($json['providers'])) {
            $json['providers'] = array_map(function ($provider) use ($moduleName) {
                return str_replace(
                    "Modules\\$moduleName\\App\\",
                    "Modules\\$moduleName\\",
                    $provider
                );
            }, $json['providers']);
        }

        File::put($moduleJsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info('   ✓ module.json corregido');
    }
}
