<?php

namespace Modules\Shared\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CheckDocumentStatusRequestData extends Data
{
    public function __construct(
        #[Required]
        public string $job_id,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->string()->description(
                'The job identifier returned by RequestDocumentGeneration.'
            ),
        ];
    }
}
