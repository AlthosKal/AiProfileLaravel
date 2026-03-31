<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Rules\RecaptchaV3Rule;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * DTO para la solicitud de inicio de sesión.
 *
 * Incluye device_name para nombrar el token Sanctum generado,
 * permitiendo al usuario identificar y revocar sesiones activas por dispositivo.
 */
class LoginData extends Data
{
    public function __construct(
        #[Rule('required|email|max:254')]
        public string $email,
        #[Password(default: true)]
        #[Rule('required')]
        public string $password,
        #[Rule('required|string|max:255')]
        public string $device_name,
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

            // Nombre del dispositivo (identifica el token Sanctum en sesiones activas)
            'device_name.required' => AuthErrorCode::DeviceNameRequired->value,
            'device_name.max' => AuthErrorCode::DeviceNameTooLong->value,

            // Recordar sesión
            'remember.boolean' => AuthErrorCode::RememberInvalidFormat->value,

            // Recaptcha Token
            'recaptcha_token.string' => AuthErrorCode::RecaptchaInvalidFormat->value,
        ];
    }
}
