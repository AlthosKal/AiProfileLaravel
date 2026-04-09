<?php

namespace Modules\Shared\Sandbox;

/**
 * Value object que representa el resultado de un job ejecutado en el sandbox.
 *
 * Inmutable: se construye una vez al finalizar la ejecución del contenedor
 * y se consume por el llamador para decidir qué hacer con el output.
 */
final readonly class SandboxJob
{
    /**
     * @param  string  $jobId  Identificador único del job (prefijo de ruta en MinIO).
     * @param  string  $stdout  Salida estándar capturada del script.
     * @param  string  $stderr  Salida de error capturada del script.
     * @param  int  $exitCode  Código de salida del proceso (0 = éxito).
     * @param  string  $outputPath  Ruta absoluta al archivo generado en el volumen de jobs, o vacío si no hubo output.
     */
    public function __construct(
        public string $jobId,
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public string $outputPath,
    ) {}

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }

    public function hasOutput(): bool
    {
        return $this->outputPath !== '' && file_exists($this->outputPath);
    }
}
