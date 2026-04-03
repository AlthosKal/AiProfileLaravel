<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('module:make-mcp-resource {name : Nombre del Resource MCP} {module : Nombre del módulo}')]
#[Description('Crea un nuevo Resource MCP dentro de un módulo')]
class ModuleMakeMcpResourceCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->argument('module');

        $modulePath = base_path("Modules/{$module}");

        if (! is_dir($modulePath)) {
            $this->error("✗ El módulo '{$module}' no existe en: {$modulePath}");

            return self::FAILURE;
        }

        $suffix = 'Resource';
        $className = Str::endsWith($name, $suffix) ? $name : $name.$suffix;

        $relativePath = "Mcp/Resources/{$className}.php";
        $fullPath = "{$modulePath}/app/{$relativePath}";
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($fullPath)) {
            $this->error("✗ El resource ya existe: Modules/{$module}/app/{$relativePath}");

            return self::FAILURE;
        }

        $namespace = "Modules\\{$module}\\Mcp\\Resources";

        $stub = $this->buildStub($namespace, $className);

        file_put_contents($fullPath, $stub);

        $this->info("MCP Resource [Modules/{$module}/app/{$relativePath}] created successfully.");

        return self::SUCCESS;
    }

    private function buildStub(string $namespace, string $className): string
    {
        return <<<PHP
        <?php

        namespace {$namespace};

        use Laravel\Mcp\Request;
        use Laravel\Mcp\Response;
        use Laravel\Mcp\Server\Attributes\Description;
        use Laravel\Mcp\Server\Resource;

        #[Description('A description of what this resource contains.')]
        class {$className} extends Resource
        {
            /**
             * Handle the resource request.
             */
            public function handle(Request \$request): Response
            {
                //

                return Response::text('The resource content.');
            }
        }
        PHP;
    }
}
