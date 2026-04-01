<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\IdentificationTypeEnum;

class IdentificationTypeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = array_column(IdentificationTypeEnum::cases(), 'value');

        if (! in_array($value, $valid)) {
            $fail(AuthErrorCode::IdentificationTypeInvalid->value);
        }
    }
}
