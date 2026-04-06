<?php

namespace Modules\Transaction\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Enums\TransactionType;

class TransactionTypeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        $valid = array_column(TransactionType::cases(), 'value');

        if (! in_array($value, $valid)) {
            $fail(TransactionErrorCode::TransactionTypeInvalid->value);
        }
    }
}
