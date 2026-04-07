<?php

namespace Modules\Transaction\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class GetTransactionsByAmountRangeRequestData extends Data
{
    public function __construct(
        #[Numeric]
        #[Required]
        public float $amount_from,
        #[Numeric]
        #[Required]
        public float $amount_to,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'amount_from' => ['lte:amount_to'],
            'amount_to' => ['gte::amount_from'],
        ];
    }

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'amount_from' => $schema->number()
                ->required()
                ->description('Minimum value from which transactions are to be retrieved.'),
            'amount_to' => $schema->number()->required()->description('Maximum value from which transactions are to be retrieved.'),
        ];
    }
}
