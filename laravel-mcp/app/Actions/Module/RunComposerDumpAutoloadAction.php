<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Support\Facades\Process;

class RunComposerDumpAutoloadAction
{
    use HasConsoleOutput;

    public function handle(): void
    {
        $this->info('📦 Ejecutando composer dump-autoload...');

        $result = Process::path(base_path())
            ->run('composer dump-autoload --optimize');

        if ($result->successful()) {
            $this->info('   ✓ Autoload regenerado correctamente');
        } else {
            $this->error('   ✗ Error al regenerar autoload');
            $this->error($result->errorOutput());
        }
    }
}
