<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('module:make-mcp-server {name : Nombre del servidor MCP} {module : Nombre del módulo}')]
#[Description('Crea un nuevo servidor MCP dentro de un módulo')]
class ModuleMakeMcpServerCommand extends Command
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

        $suffix = 'Server';
        $className = Str::endsWith($name, $suffix) ? $name : $name.$suffix;

        $relativePath = "Mcp/Servers/{$className}.php";
        $fullPath = "{$modulePath}/app/{$relativePath}";
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($fullPath)) {
            $this->error("✗ El servidor ya existe: Modules/{$module}/app/{$relativePath}");

            return self::FAILURE;
        }

        $namespace = "Modules\\{$module}\\Mcp\\Servers";
        $serverName = Str::of($className)->beforeLast('Server')->headline();

        $stub = $this->buildStub($namespace, $className, $serverName);

        file_put_contents($fullPath, $stub);

        $this->info("MCP Server [Modules/{$module}/app/{$relativePath}] created successfully.");

        return self::SUCCESS;
    }

    private function buildStub(string $namespace, string $className, string $serverName): string
    {
        return <<<PHP
        <?php

        namespace {$namespace};

        use Laravel\Mcp\Server;
        use Laravel\Mcp\Server\Attributes\Instructions;
        use Laravel\Mcp\Server\Attributes\Name;
        use Laravel\Mcp\Server\Attributes\Version;

        #[Name('{$serverName}')]
        #[Version('0.0.1')]
        #[Instructions('Instructions describing how to use the server and its features.')]
        class {$className} extends Server
        {
            protected array \$tools = [
                //
            ];

            protected array \$resources = [
                //
            ];

            protected array \$prompts = [
                //
            ];
        }
        PHP;
    }
}
