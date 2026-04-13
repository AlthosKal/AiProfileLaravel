<?php

namespace Modules\Transaction\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Modules\Shared\Enums\SandboxJobStatus;
use Modules\Shared\Http\Data\ExecuteSandboxRequestData;
use Modules\Shared\Http\Data\RequestDocumentGenerationRequestData;
use Modules\Shared\Http\Data\RequestDocumentGenerationResponseData;
use Modules\Shared\Jobs\ExecuteSandboxJob;
use Modules\Shared\Security\GatewayUser;

/**
 * Despacha la generación de un documento en segundo plano y retorna
 * un job_id para consultar el estado con CheckDocumentStatusTool.
 *
 * El script Python debe escribir su output en:
 *   os.path.join(os.environ["OUTPUT_DIR"], output_file_name)
 */
#[Title('Request Document Generation')]
#[Description('
**Description:**
Dispatches a Python script to generate a document (PDF, Excel, CSV) asynchronously in an isolated sandbox.
Returns a job_id to track progress. Use CheckDocumentStatus to poll until the document is ready.

**Parameters:**

* `code`: Python script to execute. Must write its output file to `os.path.join(os.environ["OUTPUT_DIR"], output_file_name)`.
* `output_file_name`: Name of the file the script will generate (e.g. "report.pdf", "transactions.xlsx"). Must match the filename the script writes to OUTPUT_DIR.

**Supported output formats:** PDF (.pdf), Excel (.xlsx), CSV (.csv)

**Available Python libraries:** reportlab, openpyxl, matplotlib, pandas, Pillow

**Extended description:**
The script runs in a network-isolated, read-only container with a 60-second timeout.
This tool returns immediately with a job_id. Call CheckDocumentStatus with that job_id
to poll for the result. When status is "completed", a 10-minute pre-signed download URL is returned.
')]
class RequestDocumentGenerationTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, RequestDocumentGenerationRequestData $data): ResponseFactory
    {
        /** @var GatewayUser $user */
        $user = $request->user();
        $jobId = (string) Str::uuid();

        Cache::put(
            ExecuteSandboxJob::cacheKey($jobId),
            ['status' => SandboxJobStatus::Pending->value],
            ExecuteSandboxJob::RESULT_TTL_SECONDS,
        );

        ExecuteSandboxJob::dispatch(
            jobId: $jobId,
            data: new ExecuteSandboxRequestData(
                code: $data->code,
                output_file_name: $data->output_file_name,
            ),
            userEmail: $user->email,
        );

        $result = new RequestDocumentGenerationResponseData(
            job_id: $jobId,
            status: SandboxJobStatus::Pending->value,
            message: "Document generation started. Use CheckDocumentStatus with job_id '{$jobId}' to track progress.",
        );

        return Response::make(
            Response::text("Document generation dispatched. job_id: {$jobId}. Poll CheckDocumentStatus to get the result.")
        )->withStructuredContent($result->toArray());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return RequestDocumentGenerationRequestData::toolSchema($schema);
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return RequestDocumentGenerationResponseData::toolSchema($schema);
    }
}
