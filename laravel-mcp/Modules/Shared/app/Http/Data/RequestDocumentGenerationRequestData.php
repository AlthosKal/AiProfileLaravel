<?php

namespace Modules\Shared\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class RequestDocumentGenerationRequestData extends Data
{
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
                'Python script to execute. Must write its output to os.path.join(os.environ["OUTPUT_DIR"], output_file_name).'
            ),
            'output_file_name' => $schema->string()->description(
                'Name of the file the script generates (e.g. "report.pdf", "transactions.xlsx").'
            ),
        ];
    }
}
