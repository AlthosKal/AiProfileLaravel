<?php

namespace Modules\Shared\Sandbox;

use Modules\Shared\Enums\SandboxJobConstantsEnum;
use Modules\Shared\Enums\SandboxJobTimesEnum;
use Modules\Shared\Http\Data\ExecuteSandboxRequestData;

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
     */
    public function run(ExecuteSandboxRequestData $data): SandboxJob
    {
        $jobId = uniqid('job_', more_entropy: true);
        $hostJobDir = "$this->hostJobsPath/$jobId";
        $hostOutputDir = "$hostJobDir/output";

        mkdir($hostJobDir, 0755, true);
        mkdir($hostOutputDir, 0755, true);

        $scriptHostPath = "$hostJobDir/script.py";
        file_put_contents($scriptHostPath, $data->code);

        $containerScriptPath = SandboxJobConstantsEnum::CONTAINER_JOBS_PATH->value."/$jobId/script.py";
        $containerOutputDir = SandboxJobConstantsEnum::CONTAINER_JOBS_PATH->value."/$jobId/output";

        $fullCommand = sprintf(
            'docker exec --user sandbox -e OUTPUT_DIR=%s %s timeout %d python %s 2>&1',
            escapeshellarg($containerOutputDir),
            SandboxJobConstantsEnum::CONTAINER_NAME->value,
            SandboxJobTimesEnum::TIMEOUT_SECONDS->value,
            escapeshellarg($containerScriptPath),
        );

        exec($fullCommand, $outputLines, $exitCode);

        $outputPath = "$hostOutputDir/$data->output_file_name";

        return new SandboxJob(
            jobId: $jobId,
            stdout: implode("\n", $outputLines),
            exitCode: $exitCode,
            outputPath: file_exists($outputPath) ? $outputPath : '',
        );
    }
}
