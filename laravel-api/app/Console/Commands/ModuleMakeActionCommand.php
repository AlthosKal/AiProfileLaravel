<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('module:make-action {name : Nombre de la clase Action} {module : Nombre del módulo}')]
#[Description('Crea una nueva clase Action dentro de un módulo')]
class ModuleMakeActionCommand extends Command
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

        $suffix = 'Action';
        $className = Str::endsWith($name, $suffix) ? $name : $name.$suffix;

        $relativePath = "Actions/{$className}.php";
        $fullPath = "{$modulePath}/app/{$relativePath}";
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($fullPath)) {
            $this->error("✗ La clase ya existe: Modules/{$module}/app/{$relativePath}");

            return self::FAILURE;
        }

        $namespace = "Modules\\{$module}\\Actions";

        $stub = $this->buildStub($namespace, $className);

        file_put_contents($fullPath, $stub);

        $this->info("Action [Modules/{$module}/app/{$relativePath}] created successfully.");

        return self::SUCCESS;
    }

    private function buildStub(string $namespace, string $className): string
    {
        $stubPath = base_path('stubs/action.stub');

        if (file_exists($stubPath)) {
            $stub = file_get_contents($stubPath);

            return str_replace(
                ['DummyNamespace', 'DummyClass'],
                [$namespace, $className],
                $stub,
            );
        }

        return <<<PHP
        <?php

        namespace {$namespace};

        class {$className}
        {
            public function handle(): void
            {
                //
            }
        }
        PHP;
    }
}
