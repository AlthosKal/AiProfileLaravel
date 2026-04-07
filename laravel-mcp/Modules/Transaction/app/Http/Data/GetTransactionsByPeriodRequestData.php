<?php

namespace Modules\Transaction\Http\Data;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class GetTransactionsByPeriodRequestData extends Data
{
    public function __construct(
        #[Date]
        #[Rule('required|date_format:Y-m-d')]
        public string $date_from,
        #[Date]
        #[Rule('required|date_format:Y-m-d')]
        public string $date_to,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'date_from' => ['before_or_equal:date_to'],
            'date_to' => ['after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, Type>
     */
    public static function toolSchema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()
                ->format('date')
                ->required()
                ->description('Start date of the range in (YYYY-MM-DD) format'),
            'date_to' => $schema->string()
                ->format('date')
                ->required()
                ->description('End date of the range in (YYYY-MM-DD) format'),
        ];
    }
}
