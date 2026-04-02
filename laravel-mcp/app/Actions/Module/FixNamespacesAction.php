<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class FixNamespacesAction
{
    use HasConsoleOutput;

    /**
     * @throws FileNotFoundException
     */
    public function handle(string $modulePath, string $moduleName): void
    {
        $this->info('🔧 Corrigiendo namespaces...');

        $phpFiles = File::allFiles($modulePath.'/app');

        foreach ($phpFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());

                $pattern = "/namespace Modules\\\\$moduleName\\\\App\\\\/";
                $replacement = "namespace Modules\\$moduleName\\";

                $newContent = preg_replace($pattern, $replacement, $content);

                if ($newContent !== null && $newContent !== $content) {
                    File::put($file->getPathname(), $newContent);
                    $this->info("   ✓ {$file->getRelativePathname()}");
                }
            }
        }
    }
}
