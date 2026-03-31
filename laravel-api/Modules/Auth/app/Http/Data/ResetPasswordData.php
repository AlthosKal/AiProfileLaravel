<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthErrorCode;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * DTO para la solicitud de reset de contraseña.
 *
 * Recibe el token generado por el broker, el email del usuario
 * y la nueva contraseña con su confirmación. El token y el email
 * son validados por `Password::reset()` internamente, por lo que
 * aquí solo se garantiza presencia y formato básico.
 */
class ResetPasswordData extends Data
{
    public function __construct(
        #[Rule('required|string')]
        public string $token,
        #[Rule('required|email|max:254')]
        public string $email,
        #[Password(default: true)]
        #[Rule('required|confirmed')]
        public string $password,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            // Token
            'token.required' => AuthErrorCode::TokenRequired->value,

            // Email
            'email.required' => AuthErrorCode::EmailRequired->value,
            'email.email' => AuthErrorCode::EmailInvalid->value,
            'email.max' => AuthErrorCode::EmailTooLong->value,

            // Contraseña
            'password.required' => AuthErrorCode::PasswordRequired->value,
            'password.confirmed' => AuthErrorCode::PasswordConfirmationMismatch->value,
        ];
    }
}
