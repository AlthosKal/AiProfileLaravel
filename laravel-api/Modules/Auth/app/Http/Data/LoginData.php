<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Rules\RecaptchaV3Rule;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class LoginData extends Data
{
    public function __construct(
        #[Rule('required|email|max:254')]
        public string $email,
        #[Rule('required|min:8|max:72')]
        public string $password,
        #[Rule('nullable|boolean')]
        public ?bool $remember,
        #[Rule(['nullable', 'string', new RecaptchaV3Rule('login')])]
        public ?string $recaptcha_token
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

            // Contraseña
            'password.required' => AuthErrorCode::PasswordRequired->value,
            'password.min' => AuthErrorCode::PasswordTooShort->value,
            'password.max' => AuthErrorCode::PasswordTooLong->value,

            // Recordar sesión
            'remember.boolean' => AuthErrorCode::RememberInvalidFormat->value,

            // Recaptcha Token
            'recaptcha_token.string' => AuthErrorCode::RecaptchaInvalidFormat->value,
        ];
    }
}
