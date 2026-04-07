<?php

namespace Modules\Transaction\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Data;

class TransactionData extends Data
{
    public function __construct(
        public string $name,
        public float $amount,
        public string $description,
        public string $type,
        public string $created_at,
        public string $updated_at,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Transaction name'),
            'amount' => $schema->number()->description('Transaction amount'),
            'description' => $schema->string()->description('Transaction description'),
            'type' => $schema->string()->description('Transaction type (INCOMES, EXPENSES)'),
            'created_at' => $schema->string()
                ->format('date')
                ->description('Date of the transaction creation in (YYYY-MM-DD) format'),
            'updated_at' => $schema->string()
                ->format('date')
                ->description('End date of the last transaction updated in (YYYY-MM-DD) format'),
        ];
    }
}
