<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class FixRouteFilesAction
{
    use HasConsoleOutput;

    /**
     * Cambio: use Modules\ModuleName\App\... → use Modules\ModuleName\...
     *
     * @throws FileNotFoundException
     */
    public function handle(string $modulePath, string $moduleName): void
    {
        $this->info('🔧 Corrigiendo archivos de rutas...');

        $routeFiles = [
            $modulePath.'/routes/web.php',
            $modulePath.'/routes/api.php',
        ];

        foreach ($routeFiles as $routeFile) {
            if (! File::exists($routeFile)) {
                continue;
            }

            $content = File::get($routeFile);

            $pattern = "/use Modules\\\\$moduleName\\\\App\\\\/";
            $replacement = "use Modules\\$moduleName\\";

            $newContent = preg_replace($pattern, $replacement, $content);

            if ($newContent !== null && $newContent !== $content) {
                File::put($routeFile, $newContent);
                $this->info('   ✓ '.basename($routeFile));
            }
        }
    }
}
