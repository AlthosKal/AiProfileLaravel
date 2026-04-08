<?php

namespace Modules\Transaction\Http\Data\Response;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Modules\Transaction\Http\Data\TransactionData;
use Spatie\LaravelData\Data;

class GetTransactionsPaginatedData extends Data
{
    /**
     * @param  array<int, mixed>  $data
     */
    public function __construct(
        public array $data,
        public float $total_amount,
        public int $total,
        public int $per_page,
        public int $current_page,
        public int $last_page,
    ) {}

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->array()
                ->description('List of transactions')
                ->items($schema->object(TransactionData::toolSchema($schema))),
            'total' => $schema->integer()->description('Total number of transactions in the database'),
            'per_page' => $schema->integer()->description('Number of transactions per page'),
            'current_page' => $schema->integer()->description('Current page number'),
            'last_page' => $schema->integer()->description('Last available page number'),
        ];
    }
}
