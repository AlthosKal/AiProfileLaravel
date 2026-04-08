<?php

namespace Modules\Transaction\Http\Data\Request;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Modules\Transaction\Enums\TransactionType;
use Modules\Transaction\Rules\TransactionTypeRule;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class GetTransactionsByTypeRequestData extends Data
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
        #[Required]
        #[Rule(['string', new TransactionTypeRule])]
        public string $type,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->required()
                ->enum(TransactionType::cases())
                ->description('Transaction type to filter by. Accepted values: "income" or "expense".'),
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
