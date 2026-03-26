<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RecaptchaV3Rule implements ValidationRule
{
    public function __construct(
        private string $action,
        private float $score,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        // Si no hay Token, fallar
        if (empty($value) || ! is_string($value)) {
            $fail(trans('auth.captcha_verification_required'));
        }
    }
}
