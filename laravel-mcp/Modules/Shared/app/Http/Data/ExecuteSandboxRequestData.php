<?php

namespace Modules\Shared\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ExecuteSandboxRequestData extends Data
{
    /**
     * @param  string  $code  Código Python a ejecutar.
     * @param  string  $output_file_name  Nombre del archivo que el script debe escribir en /sandbox/jobs/{jobId}/output/.
     */
    public function __construct(
        #[Required]
        public string $code,
        #[Required]
        public string $output_file_name,

    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
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
