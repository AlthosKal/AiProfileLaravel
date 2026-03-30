<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Auth\Actions\Auth\RecaptchaVerificationAction;
use Modules\Auth\Enums\AuthErrorCode;

readonly class RecaptchaV3Rule implements ValidationRule
{
    public function __construct(
        private string $action,
    ) {}

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || ! is_string($value)) {
            $fail(AuthErrorCode::CaptchaVerificationRequired->value);

            return;
        }

        $result = app(RecaptchaVerificationAction::class)->verify($value, $this->action);

        if (! $result['success']) {
            $fail(AuthErrorCode::CaptchaVerificationFailed->value);
        }
    }
}
