<?php

namespace Modules\Shared\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Data;

class CheckDocumentStatusResponseData extends Data
{
    public function __construct(
        public string $job_id,
        public string $status,
        public ?string $download_url,
        public ?string $file_name,
        public ?int $expired_in_minutes,
        public ?string $error_message,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->string()->description('The job identifier.'),
            'status' => $schema->string()->description('Current status: pending, processing, completed, or failed.'),
            'download_url' => $schema->string()->description('Pre-signed download URL. Only present when status is completed.')->nullable(),
            'file_name' => $schema->string()->description('Generated file name. Only present when status is completed.')->nullable(),
            'expired_in_minutes' => $schema->integer()->description('URL expiry time in minutes. Only present when status is completed.')->nullable(),
            'error_message' => $schema->string()->description('Error details. Only present when status is failed.')->nullable(),
        ];
    }
}
