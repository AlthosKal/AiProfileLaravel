<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Support\Facades\Process;

class RunPintAction
{
    use HasConsoleOutput;

    public function handle(string $modulePath): void
    {
        $this->info('🎨 Ejecutando Laravel Pint...');

        $result = Process::path(base_path())
            ->run("./vendor/bin/pint $modulePath");

        if ($result->successful()) {
            $this->info('   ✓ Pint ejecutado correctamente');
        } else {
            $this->error('   ✗ Error al ejecutar Pint');
            $this->error($result->errorOutput());
        }
    }
}
