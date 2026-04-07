<?php

namespace Modules\Transaction\Http\Data;

use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Rules\TransactionTypeRule;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class AddOrUpdateTransactionData extends Data
{
    public function __construct(
        #[Rule('required|string')]
        public string $name,
        #[Rule('required|integer')]
        public string $amount,
        #[Rule('required|string')]
        public string $description,
        #[Rule(['required', 'string', new TransactionTypeRule])]
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
