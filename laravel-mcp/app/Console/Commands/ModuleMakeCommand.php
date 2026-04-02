<?php

namespace App\Console\Commands;

use App\Events\ModuleCreatedEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

#[Signature('module:make-new {name : Nombre del módulo a crear}')]
#[Description('Crea un nuevo módulo y lo formatea automáticamente')]
class ModuleMakeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $moduleName = $this->argument('name');

        $this->info("🚀 Creando módulo: $moduleName");
        $this->newLine();

        // Ejecutar el comando original de nwidart
        $exitCode = Artisan::call('module:make', ['name' => [$moduleName]]);

        if ($exitCode !== 0) {
            $this->error('✗ Error al crear el módulo');
            $this->error(Artisan::output());

            return self::FAILURE;
        }

        // Mostrar output del comando original
        $this->line(Artisan::output());

        // Obtener la ruta del módulo creado
        $modulePath = base_path("Modules/$moduleName");

        if (! is_dir($modulePath)) {
            $this->error("✗ El módulo fue creado pero no se encontró en: $modulePath");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('   FORMATEO AUTOMÁTICO');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Disparar el evento para formatear el módulo
        event(new ModuleCreatedEvent($moduleName, $modulePath));

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        return self::SUCCESS;
    }
}
