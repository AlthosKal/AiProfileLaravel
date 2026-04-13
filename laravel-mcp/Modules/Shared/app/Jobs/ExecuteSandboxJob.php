<?php

namespace Modules\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Modules\Shared\Builders\SandboxPathBuilder;
use Modules\Shared\Enums\JobErrorType;
use Modules\Shared\Enums\SandboxJobStatus;
use Modules\Shared\Http\Data\ExecuteSandboxRequestData;
use Modules\Shared\Sandbox\SandboxJobRunner;
use Modules\Shared\Stores\CloudObjectStorage;
use Modules\Transaction\Enums\FileType;
use Modules\Transaction\Models\File;
use Throwable;

class ExecuteSandboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Segundos que el resultado permanece en Redis tras completarse.
     */
    const int RESULT_TTL_SECONDS = 600;

    /**
     * Tiempo máximo que el job puede correr (debe exceder el timeout del sandbox).
     */
    public int $timeout = 90;

    /**
     * Intentos máximos — sin reintentos para ejecuciones de código generado por IA.
     */
    public int $tries = 1;

    public function __construct(
        public readonly string $jobId,
        public readonly ExecuteSandboxRequestData $data,
        public readonly string $userEmail,
    ) {}

    public function handle(SandboxJobRunner $runner): void
    {
        $this->updateStatus(SandboxJobStatus::Processing);

        $sandboxJob = $runner->run($this->data);

        if (! $sandboxJob->succeeded()) {
            $this->updateStatus(SandboxJobStatus::Failed, errorMessage: $sandboxJob->stdout);

            return;
        }

        if (! $sandboxJob->hasOutput()) {
            $this->updateStatus(
                SandboxJobStatus::Failed,
                errorMessage: "Script succeeded but did not produce the expected file '{$this->data->output_file_name}'. stdout:\n{$sandboxJob->stdout}",
            );

            return;
        }

        $storagePath = SandboxPathBuilder::buildForJob($sandboxJob->jobId, $this->data->output_file_name);
        CloudObjectStorage::storeFromPath($storagePath, $sandboxJob->outputPath);

        File::create([
            'user_email' => $this->userEmail,
            'name' => $this->data->output_file_name,
            'path' => $storagePath,
            'type' => FileType::GENERATED,
        ]);

        $downloadUrl = CloudObjectStorage::temporaryUrl($storagePath, minutes: 10);

        $this->updateStatus(
            SandboxJobStatus::Completed,
            downloadUrl: $downloadUrl,
            fileName: $this->data->output_file_name,
            expiredInMinutes: 10,
        );
    }

    public function failed(Throwable $exception): void
    {
        $this->updateStatus(SandboxJobStatus::Failed, errorMessage: $exception->getMessage());
    }

    /**
     * Clave Redis para este job.
     */
    public static function cacheKey(string $jobId): string
    {
        return "sandbox_job:{$jobId}";
    }

    /**
     * Escribe el estado actual del job en Redis.
     */
    private function updateStatus(
        SandboxJobStatus $status,
        ?string $downloadUrl = null,
        ?string $fileName = null,
        ?int $expiredInMinutes = null,
        ?string $errorMessage = null,
    ): void {
        Cache::put(
            self::cacheKey($this->jobId),
            [
                'status' => $status->value,
                'error_type' => match ($status) {
                    SandboxJobStatus::Failed => JobErrorType::EXECUTION_FAILED->value,
                    SandboxJobStatus::Completed => JobErrorType::NO_ERROR->value,
                    default => null,
                },
                'download_url' => $downloadUrl,
                'file_name' => $fileName,
                'expired_in_minutes' => $expiredInMinutes,
                'error_message' => $errorMessage,
            ],
            self::RESULT_TTL_SECONDS,
        );
    }
}
