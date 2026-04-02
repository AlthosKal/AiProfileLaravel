<?php

namespace App\Actions\Module;

use App\Actions\Module\Concerns\HasConsoleOutput;
use Illuminate\Support\Facades\Process;

class RunLastanAction
{
    use HasConsoleOutput;

    public function handle(string $modulePath): void
    {
        $this->info('🔍 Ejecutando Larastan (PHPStan)...');

        $result = Process::path(base_path())
            ->timeout(120)
            ->run("./vendor/bin/phpstan analyse $modulePath --level=6");

        if ($result->successful()) {
            $this->success('   ✓ Larastan: Código validado correctamente (nivel 6)');
        } else {
            $this->warn('   ⚠ Larastan encontró algunos problemas:');
            $this->warn($result->errorOutput());
            $this->warn('   → Revisa y corrige manualmente si es necesario');
        }
    }
}
