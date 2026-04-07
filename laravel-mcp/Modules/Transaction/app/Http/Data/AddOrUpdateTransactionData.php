<?php

namespace Modules\Transaction\Http\Data;

use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Rules\TransactionTypeRule;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class AddOrUpdateTransactionData extends Data
{
    public function __construct(
        #[Required]
        #[Rule('string')]
        public string $name,
        #[Numeric]
        #[Required]
        public float $amount,
        #[Required]
        #[Rule('string')]
        public string $description,
        #[Required]
        #[Rule(['string', new TransactionTypeRule])]
        public string $type,
    ) {}

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => TransactionErrorCode::NameRequired->value,

            'amount.required' => TransactionErrorCode::AmountRequired->value,
            'description.required' => TransactionErrorCode::DescriptionRequired->value,
            'type.required' => TransactionErrorCode::TransactionTypeRequired->value,
        ];
    }
}
