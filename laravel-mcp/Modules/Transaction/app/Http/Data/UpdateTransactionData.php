<?php

namespace Modules\Transaction\Http\Data;

use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Rules\TransactionTypeRule;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class UpdateTransactionData extends Data
{
    public function __construct(
        #[Rule('required|integer')]
        public int $id,
        #[Rule('required|string')]
        public string $name,
        #[Rule('required|integer')]
        public string $amount,
        #[Rule('required|string')]
        public string $description,
        #[Rule(['required','string', new TransactionTypeRule()])]
        public string $type,
    ) {}

    public static function messages(): array
    {
        return [
            'id.required' => TransactionErrorCode::IdRequired->value,
            'name.required' => TransactionErrorCode::NameRequired->value,

            'amount.required' => TransactionErrorCode::AmountRequired->value,
            'description.required' => TransactionErrorCode::DescriptionRequired->value,
            'type.required' => TransactionErrorCode::TransactionTypeRequired->value,
        ];
    }
}
