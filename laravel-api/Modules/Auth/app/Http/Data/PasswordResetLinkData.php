<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Rules\RecaptchaV3Rule;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class PasswordResetLinkData extends Data
{
    public function __construct(
        #[Rule('required|email|max:254')]
        public string $email,
        #[Rule(['nullable', 'string', new RecaptchaV3Rule('forgot_password')])]
        public ?string $recaptcha_token = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            // Email
            'email.required' => AuthErrorCode::EmailRequired->value,
            'email.email' => AuthErrorCode::EmailInvalid->value,
            'email.max' => AuthErrorCode::EmailTooLong->value,

            // Recaptcha Token
            'recaptcha_token.string' => AuthErrorCode::RecaptchaInvalidFormat->value,
        ];
    }
}
