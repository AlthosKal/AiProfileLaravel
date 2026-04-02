<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class FixReturnTypesAction
{
    use HasConsoleOutput;

    /**
     * @throws FileNotFoundException
     */
    public function handle(string $modulePath): void
    {
        $this->info('🔧 Agregando return types...');

        $controllerPath = $modulePath.'/app/Http/Controllers';

        if (! File::isDirectory($controllerPath)) {
            return;
        }

        $controllers = File::allFiles($controllerPath);

        foreach ($controllers as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());

                $patterns = [
                    '/public function index\(\)\s*\n\s*\{/' => "/**\n     * @phpstan-return \\Illuminate\\Contracts\\View\\View\n     */\n    public function index(): \\Illuminate\\Contracts\\View\\View\n    {",
                    '/public function create\(\)\s*\n\s*\{/' => "/**\n     * @phpstan-return \\Illuminate\\Contracts\\View\\View\n     */\n    public function create(): \\Illuminate\\Contracts\\View\\View\n    {",
                    '/public function show\(\$id\)\s*\n\s*\{/' => "/**\n     * @phpstan-return \\Illuminate\\Contracts\\View\\View\n     */\n    public function show(string \$id): \\Illuminate\\Contracts\\View\\View\n    {",
                    '/public function edit\(\$id\)\s*\n\s*\{/' => "/**\n     * @phpstan-return \\Illuminate\\Contracts\\View\\View\n     */\n    public function edit(string \$id): \\Illuminate\\Contracts\\View\\View\n    {",
                    '/public function store\(Request \$request\)\s*\{\}/' => "public function store(Request \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        // TODO: Implement store logic\n        return redirect()->back();\n    }",
                    '/public function update\(Request \$request, \$id\)\s*\{\}/' => "public function update(Request \$request, string \$id): \\Illuminate\\Http\\RedirectResponse\n    {\n        // TODO: Implement update logic\n        return redirect()->back();\n    }",
                    '/public function destroy\(\$id\)\s*\{\}/' => "public function destroy(string \$id): \\Illuminate\\Http\\RedirectResponse\n    {\n        // TODO: Implement destroy logic\n        return redirect()->back();\n    }",
                ];

                $newContent = $content;
                foreach ($patterns as $pattern => $replacement) {
                    $newContent = preg_replace($pattern, $replacement, $newContent);
                }

                if ($newContent !== null && $newContent !== $content) {
                    File::put($file->getPathname(), $newContent);
                    $this->info("   ✓ {$file->getRelativePathname()}");
                }
            }
        }
    }
}
