<?php

namespace Modules\Shared\Actions;

use Modules\Shared\Builders\SandboxPathBuilder;
use Modules\Shared\Enums\JobErrorType;
use Modules\Shared\Http\Data\ExecuteSandboxRequestData;
use Modules\Shared\Http\Data\ExecuteSandboxResponseData;
use Modules\Shared\Sandbox\SandboxJob;
use Modules\Shared\Sandbox\SandboxJobRunner;
use Modules\Shared\Security\GatewayUser;
use Modules\Shared\Stores\CloudObjectStorage;
use Modules\Transaction\Enums\FileType;
use Modules\Transaction\Models\File;

readonly class ExecuteSandboxAction
{
    public function __construct(
        private SandboxJobRunner $runner,
    ) {}

    public function execute(ExecuteSandboxRequestData $data, GatewayUser $user): ExecuteSandboxResponseData
    {
        $job = $this->runner->run($data);

        if ($failure = $this->checkJobFailure($job, $data->output_file_name)) {
            return $failure;
        }

        $storagePath = SandboxPathBuilder::buildForJob($job->jobId, $data->output_file_name);
        CloudObjectStorage::storeFromPath($storagePath, $job->outputPath);

        File::create([
            'user_email' => $user->email,
            'name' => $data->output_file_name,
            'path' => $storagePath,
            'type' => FileType::GENERATED,
        ]);

        $downloadUrl = CloudObjectStorage::temporaryUrl($storagePath, minutes: 10);

        return new ExecuteSandboxResponseData(
            errorType: JobErrorType::NO_ERROR,
            downloadUrl: $downloadUrl,
            fileName: $data->output_file_name,
            expiredInMinutes: 10,
            errorMessage: null,
        );
    }

    private function checkJobFailure(SandboxJob $job, string $outputFileName): ?ExecuteSandboxResponseData
    {
        if (! $job->succeeded()) {
            return new ExecuteSandboxResponseData(
                errorType: JobErrorType::EXECUTION_FAILED,
                downloadUrl: null,
                fileName: null,
                expiredInMinutes: null,
                errorMessage: $job->stdout,
            );
        }

        if (! $job->hasOutput()) {
            return new ExecuteSandboxResponseData(
                errorType: JobErrorType::GENERATED_FAILED,
                downloadUrl: null,
                fileName: null,
                expiredInMinutes: null,
                errorMessage: "Script succeeded but did not produce the expected file '$outputFileName'. stdout:\n$job->stdout",
            );
        }

        return null;
    }
}
