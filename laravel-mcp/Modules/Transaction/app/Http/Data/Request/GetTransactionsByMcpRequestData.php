<?php

namespace Modules\Transaction\Http\Data\Request;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class GetTransactionsByMcpRequestData extends Data
{
    public function __construct(
        #[Required]
        #[IntegerType]
        #[Min(1)]
        public int $per_page,
        #[Required]
        #[IntegerType]
        #[Min(1)]
        public int $page,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'per_page' => $schema->integer()
                ->required()
                ->min(1)
                ->description('Number of transactions per page. Must be at least 1.'),
            'page' => $schema->integer()
                ->required()
                ->min(1)
                ->description('Page number to retrieve. Must be at least 1.'),
        ];
    }
}
