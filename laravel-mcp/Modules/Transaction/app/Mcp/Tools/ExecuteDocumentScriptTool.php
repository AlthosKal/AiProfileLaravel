<?php

namespace Modules\Transaction\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Modules\Shared\Builders\SandboxPathBuilder;
use Modules\Shared\Sandbox\SandboxJobRunner;
use Modules\Shared\Stores\CloudObjectStorage;

/**
 * Ejecuta un script Python en el sandbox aislado y devuelve una URL de descarga
 * temporal del archivo generado (PDF, Excel, etc.).
 *
 * El script debe escribir su output en el directorio indicado por la variable
 * de entorno OUTPUT_DIR que el sandbox inyecta automáticamente. El nombre del
 * archivo de output debe coincidir con el parámetro `output_filename`.
 *
 * Ejemplo de uso correcto en el script Python:
 *
 *   import os
 *   output_path = os.path.join(os.environ["OUTPUT_DIR"], "reporte.pdf")
 *   # ... generar el archivo ...
 *   pdf.save(output_path)
 */
#[Title('Execute Document Script')]
#[Description('
**Description:**
Executes a Python script inside an isolated sandbox and returns a temporary download URL for the generated file.

**Parameters:**

* `code`: Python script to execute. The script MUST write its output file to the path: `os.path.join(os.environ["OUTPUT_DIR"], output_filename)`.
* `output_filename`: Name of the file the script will generate (e.g. "report.pdf", "transactions.xlsx"). Must match the filename the script writes to OUTPUT_DIR.

**Supported output formats:** PDF (.pdf), Excel (.xlsx), CSV (.csv)

**Available Python libraries:** reportlab, openpyxl, matplotlib, pandas, Pillow

**Extended description:**
The script runs in a network-isolated, read-only container with a 60-second timeout.
On success, the generated file is uploaded to private object storage and a 10-minute
pre-signed download URL is returned. On failure, the script\'s error output is returned.
')]
class ExecuteDocumentScriptTool extends Tool
{
    public function __construct(
        private readonly SandboxJobRunner $runner,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $code = $request->get('code');
        $outputFilename = $request->get('output_filename');

        if (blank($code) || blank($outputFilename)) {
            return Response::make(
                Response::text('Parameters `code` and `output_filename` are required.')
            );
        }

        $job = $this->runner->run($code, $outputFilename);

        if (! $job->succeeded()) {
            return Response::make(
                Response::text("Script execution failed (exit code $job->exitCode):\n$job->stdout")
            );
        }

        if (! $job->hasOutput()) {
            return Response::make(
                Response::text("Script succeeded but did not produce the expected file '$outputFilename'. stdout:\n$job->stdout")
            );
        }

        $storagePath = SandboxPathBuilder::buildForJob($job->jobId, $outputFilename);
        CloudObjectStorage::storeFromPath($storagePath, $job->outputPath);
        $downloadUrl = CloudObjectStorage::temporaryUrl($storagePath, minutes: 10);

        return Response::make(
            Response::text("Document generated successfully. Download URL (valid 10 minutes):\n$downloadUrl")
        )->withStructuredContent([
            'download_url' => $downloadUrl,
            'filename' => $outputFilename,
            'expires_in_minutes' => 10,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description(
                'Python script to execute. Must write its output to os.path.join(os.environ["OUTPUT_DIR"], output_filename).'
            ),
            'output_filename' => $schema->string()->description(
                'Name of the file the script generates (e.g. "report.pdf", "transactions.xlsx").'
            ),
        ];
    }
}
