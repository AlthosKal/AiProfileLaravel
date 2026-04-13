<?php

namespace Modules\Transaction\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Modules\Shared\Enums\SandboxJobStatus;
use Modules\Shared\Http\Data\CheckDocumentStatusRequestData;
use Modules\Shared\Http\Data\CheckDocumentStatusResponseData;
use Modules\Shared\Jobs\ExecuteSandboxJob;

/**
 * Consulta el estado de un job de generación de documentos
 * previamente despachado por RequestDocumentGenerationTool.
 */
#[Title('Check Document Status')]
#[Description('
**Description:**
Checks the current status of a document generation job dispatched by RequestDocumentGeneration.

**Parameters:**

* `job_id`: The identifier returned by RequestDocumentGeneration.

**Possible statuses:**

* `pending` — Job is queued, not yet started.
* `processing` — Sandbox is executing the script.
* `completed` — Document is ready. A 10-minute pre-signed download URL is included.
* `failed` — Script execution failed. An error message is included.

**Extended description:**
Poll this tool after calling RequestDocumentGeneration until status is "completed" or "failed".
Results are retained for 10 minutes after completion.
')]
class CheckDocumentStatusTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, CheckDocumentStatusRequestData $data): ResponseFactory
    {
        $cached = Cache::get(ExecuteSandboxJob::cacheKey($data->job_id));

        if ($cached === null) {
            $result = new CheckDocumentStatusResponseData(
                job_id: $data->job_id,
                status: SandboxJobStatus::Failed->value,
                download_url: null,
                file_name: null,
                expired_in_minutes: null,
                error_message: "Job '{$data->job_id}' not found. It may have expired or never existed.",
            );

            return Response::make(
                Response::text("Job not found: {$data->job_id}")
            )->withStructuredContent($result->toArray());
        }

        $status = SandboxJobStatus::from($cached['status']);

        $result = new CheckDocumentStatusResponseData(
            job_id: $data->job_id,
            status: $status->value,
            download_url: $cached['download_url'] ?? null,
            file_name: $cached['file_name'] ?? null,
            expired_in_minutes: $cached['expired_in_minutes'] ?? null,
            error_message: $cached['error_message'] ?? null,
        );

        $text = match ($status) {
            SandboxJobStatus::Pending => "Job {$data->job_id} is queued. Please check again shortly.",
            SandboxJobStatus::Processing => "Job {$data->job_id} is currently running in the sandbox.",
            SandboxJobStatus::Completed => "Document ready. Download URL (valid {$result->expired_in_minutes} minutes): {$result->download_url}",
            SandboxJobStatus::Failed => "Job {$data->job_id} failed: {$result->error_message}",
        };

        return Response::make(
            Response::text($text)
        )->withStructuredContent($result->toArray());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return CheckDocumentStatusRequestData::toolSchema($schema);
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return CheckDocumentStatusResponseData::toolSchema($schema);
    }
}
