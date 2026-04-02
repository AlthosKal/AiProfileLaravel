<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class FixServiceProviderRegisterViewsAction
{
    use HasConsoleOutput;

    /**
     * Cambios:
     * - Mueve la verificación del directorio al inicio
     * - Agrega early return si el directorio no existe
     * - Formatea las llamadas a métodos con mejor indentación
     *
     * @throws FileNotFoundException
     */
    public function handle(string $modulePath, string $moduleName): void
    {
        $this->info('🔧 Corrigiendo registerViews() en ServiceProvider...');

        $serviceProviderPath = $modulePath."/app/Providers/{$moduleName}ServiceProvider.php";

        if (! File::exists($serviceProviderPath)) {
            $this->warn('   ⚠ ServiceProvider no encontrado');

            return;
        }

        $content = File::get($serviceProviderPath);

        if (str_contains($content, 'if (! is_dir($sourcePath))')) {
            $this->info('   → registerViews() ya tiene la estructura correcta');

            return;
        }

        $pattern = '/(\/\*\*\s*\n\s*\*\s*Register views\.\s*\n\s*\*\/\s*\n\s*public function registerViews\(\): void\s*\{)\s*\$viewPath = resource_path\(\'views\/modules\/\'\.\$this->nameLower\);\s*\$sourcePath = module_path\(\$this->name, \'resources\/views\'\);\s*\$this->publishes\(\[\$sourcePath => \$viewPath], \[\'views\', \$this->nameLower\.\'[^\']+\']\);\s*\$this->loadViewsFrom\(array_merge\(\$this->getPublishableViewPaths\(\), \[\$sourcePath]\), \$this->nameLower\);\s*Blade::componentNamespace\(config\(\'modules\.namespace\'\)\.\'.+?\', \$this->nameLower\);\s*}/s';

        $replacement = <<<'PHP'
    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $sourcePath = module_path($this->name, 'resources/views');

        if (! is_dir($sourcePath)) {
            return;
        }

        $viewPath = resource_path('views/modules/'.$this->nameLower);

        $this->publishes(
            [$sourcePath => $viewPath],
            ['views', $this->nameLower.'-module-views']
        );

        $this->loadViewsFrom(
            array_merge($this->getPublishableViewPaths(), [$sourcePath]),
            $this->nameLower
        );

        Blade::componentNamespace(
            config('modules.namespace').'\\\\'.$this->name.'\\\\View\\\\Components',
            $this->nameLower
        );
    }
PHP;

        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent !== null && $newContent !== $content) {
            File::put($serviceProviderPath, $newContent);
            $this->info('   ✓ registerViews() corregido');
        } else {
            $this->info('   ⚠ No se pudo corregir registerViews() - patrón no coincide');
        }
    }
}
