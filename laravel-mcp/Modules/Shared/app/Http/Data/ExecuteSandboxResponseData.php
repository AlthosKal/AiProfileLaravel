<?php

namespace Modules\Shared\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Modules\Shared\Enums\JobErrorType;
use Spatie\LaravelData\Data;

class ExecuteSandboxResponseData extends Data
{
    public function __construct(
        public JobErrorType $errorType,
        public ?string $downloadUrl,
        public ?string $fileName,
        public ?int $expiredInMinutes,
        public ?string $errorMessage,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'downloadUrl' => $schema->string()->description('URL to download the generated file.')->nullable(),
            'fileName' => $schema->string()->description('Name of the generated file.')->nullable(),
            'expiredInMinutes' => $schema->integer()->description('URL expiry time in minutes.')->nullable(),
            'errorMessage' => $schema->string()->description('Error details if generation failed.')->nullable(),
        ];
    }
}
