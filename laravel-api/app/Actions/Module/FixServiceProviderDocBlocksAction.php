<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class FixServiceProviderDocBlocksAction
{
    use HasConsoleOutput;

    /**
     * @throws FileNotFoundException
     */
    public function handle(string $modulePath, string $moduleName): void
    {
        $this->info('🔧 Agregando PHPDoc a ServiceProvider...');

        $serviceProviderPath = $modulePath."/app/Providers/{$moduleName}ServiceProvider.php";

        if (! File::exists($serviceProviderPath)) {
            $this->warn('   ⚠ ServiceProvider no encontrado');

            return;
        }

        $content = File::get($serviceProviderPath);

        $pattern1 = '/(\/\*\*\s*\n\s*\*\s*Get the services provided by the provider\.\s*\n\s*\*\/\s*\n\s*public function provides\(\): array)/';
        $replacement1 = "/**\n     * Get the services provided by the provider.\n     *\n     * @return array<int, string>\n     */\n    public function provides(): array";

        $pattern2 = '/(\n\s*private function getPublishableViewPaths\(\): array)/';
        $replacement2 = "\n    /**\n     * @return array<int, string>\n     */\n    private function getPublishableViewPaths(): array";

        $newContent = preg_replace($pattern1, $replacement1, $content);
        if ($newContent !== null) {
            $newContent = preg_replace($pattern2, $replacement2, $newContent);
        }

        if ($newContent !== null && $newContent !== $content) {
            File::put($serviceProviderPath, $newContent);
            $this->info('   ✓ ServiceProvider actualizado');
        }
    }
}
