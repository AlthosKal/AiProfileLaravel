<?php

namespace Modules\Shared\Sandbox;

use Modules\Shared\Enums\SandboxJobConstantsEnum;
use Modules\Shared\Enums\SandboxJobTimesEnum;

/**
 * Ejecuta scripts Python dentro del contenedor sandbox de forma aislada.
 *
 * Escribe el script en el volumen compartido con el contenedor, lo ejecuta
 * via `docker exec` con límite de tiempo, y recoge el resultado.
 *
 * El contenedor `mcp-sandbox-python` debe estar corriendo (sleep infinity).
 * El volumen `mcp_sandbox_jobs` debe estar montado en /sandbox/jobs tanto
 * en el contenedor sandbox como accesible desde el host en SANDBOX_JOBS_PATH.
 */
readonly class SandboxJobRunner
{
    public function __construct(
        private string $hostJobsPath,
    ) {}

    /**
     * Ejecuta el código Python dado y devuelve el resultado del job.
     *
     * @param  string  $code  Código Python a ejecutar.
     * @param  string  $outputFilename  Nombre del archivo que el script debe escribir en /sandbox/jobs/{jobId}/output/.
     */
    public function run(string $code, string $outputFilename): SandboxJob
    {
        $jobId = uniqid('job_', more_entropy: true);
        $hostJobDir = "$this->hostJobsPath/$jobId";
        $hostOutputDir = "$hostJobDir/output";

        mkdir($hostJobDir, 0755, true);
        mkdir($hostOutputDir, 0755, true);

        $scriptHostPath = "$hostJobDir/script.py";
        file_put_contents($scriptHostPath, $code);

        $containerScriptPath = SandboxJobConstantsEnum::CONTAINER_JOBS_PATH->value."/$jobId/script.py";
        $containerOutputDir = SandboxJobConstantsEnum::CONTAINER_JOBS_PATH->value."/$jobId/output";

        // Inyectar OUTPUT_DIR como variable de entorno para que el script sepa dónde escribir
        $fullCommand = sprintf(
            'docker exec --user sandbox -e OUTPUT_DIR=%s %s timeout %d python %s 2>&1',
            escapeshellarg($containerOutputDir),
            SandboxJobConstantsEnum::CONTAINER_NAME->value,
            SandboxJobTimesEnum::TIMEOUT_SECONDS->value,
            escapeshellarg($containerScriptPath),
        );

        exec($fullCommand, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);

        $outputPath = "$hostOutputDir/$outputFilename";

        return new SandboxJob(
            jobId: $jobId,
            stdout: $output,
            stderr: '',
            exitCode: $exitCode,
            outputPath: file_exists($outputPath) ? $outputPath : '',
        );
    }
}
