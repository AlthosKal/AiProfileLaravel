<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event disparado cuando se crea un nuevo módulo.
 *
 * Este evento se dispara después de ejecutar `module:make` y permite
 * realizar acciones automáticas como formateo, corrección de namespaces,
 * y validación de código.
 */
class ModuleCreatedEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $moduleName  Nombre del módulo creado (ej: "TestExample")
     * @param  string  $modulePath  Ruta absoluta al módulo (ej: "/path/to/Modules/TestExample")
     */
    public function __construct(
        public readonly string $moduleName,
        public readonly string $modulePath,
    ) {}
}
