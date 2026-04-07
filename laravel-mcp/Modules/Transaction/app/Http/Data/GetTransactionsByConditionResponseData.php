<?php

namespace Modules\Transaction\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Data;

class GetTransactionsByConditionResponseData extends Data
{
    /**
     * @param  array<int, mixed>  $transaction_data
     */
    public function __construct(
        public array $transaction_data,
        public int $transaction_count,
        public float $total_amount,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'transaction_data' => $schema->array()
                ->description('List of transactions')
                ->items($schema->object(TransactionData::toolSchema($schema))),
            'transaction_count' => $schema->integer()->description('Count of total transactions'),
            'total_amount' => $schema->number()->description('Total amount'),
        ];
    }
}
