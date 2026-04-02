<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('module:make-data {name : Nombre de la clase Data} {module : Nombre del módulo}')]
#[Description('Crea una nueva clase Data dentro de un módulo')]
class ModuleMakeDataCommand extends Command
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

        $suffix = 'Data';
        $className = Str::endsWith($name, $suffix) ? $name : $name.$suffix;

        $relativePath = "Http/Data/{$className}.php";
        $fullPath = "{$modulePath}/app/{$relativePath}";
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($fullPath)) {
            $this->error("✗ La clase ya existe: Modules/{$module}/app/{$relativePath}");

            return self::FAILURE;
        }

        $namespace = "Modules\\{$module}\\Http\\Data";

        $stub = $this->buildStub($namespace, $className);

        file_put_contents($fullPath, $stub);

        $this->info("Data [Modules/{$module}/app/{$relativePath}] created successfully.");

        return self::SUCCESS;
    }

    private function buildStub(string $namespace, string $className): string
    {
        $stubPath = base_path('stubs/data.stub');

        if (! file_exists($stubPath)) {
            $stubPath = base_path('vendor/spatie/laravel-data/stubs/data.stub');
        }

        $stub = file_get_contents($stubPath);

        return str_replace(
            ['DummyNamespace', 'DummyClass'],
            [$namespace, $className],
            $stub,
        );
    }
}
