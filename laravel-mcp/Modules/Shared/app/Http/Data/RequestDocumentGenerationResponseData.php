<?php

namespace Modules\Shared\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Data;

class RequestDocumentGenerationResponseData extends Data
{
    public function __construct(
        public string $job_id,
        public string $status,
        public string $message,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->string()->description('Unique identifier to track the document generation job.'),
            'status' => $schema->string()->description('Initial job status. Always "pending" on dispatch.'),
            'message' => $schema->string()->description('Human-readable confirmation message.'),
        ];
    }
}
