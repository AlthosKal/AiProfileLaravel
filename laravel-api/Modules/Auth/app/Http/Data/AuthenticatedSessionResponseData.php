<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthErrorCode;

/**
 * DTO que encapsula la respuesta JSON del endpoint de login.
 *
 * Comunica al frontend el estado post-login mediante claves semánticas
 * para que pueda reaccionar de manera apropiada:
 *
 *   - twoFactorCode:          redirigir al desafío 2FA
 *   - emailVerificationCode:  redirigir a verificación de email
 *   - passwordExpirationCode: mostrar advertencia de contraseña próxima a vencer
 */
final readonly class AuthenticatedSessionResponseData
{
    public function __construct(
        public ?string $twoFactorCode,
        public ?string $emailVerificationCode,
        public ?string $passwordExpirationCode,
        public ?int $daysUntilPasswordExpires,
    ) {}

    /**
     * Construir desde el resultado interno del LoginAction.
     */
    public static function fromLoginResponse(LoginResponseData $data): self
    {
        return new self(
            twoFactorCode: $data->twoFactorRequired ? AuthErrorCode::TwoFactorRequired->value : null,
            emailVerificationCode: $data->emailVerificationRequired ? AuthErrorCode::EmailVerificationRequired->value : null,
            passwordExpirationCode: $data->passwordExpiringSoon ? AuthErrorCode::PasswordExpiringSoon->value : null,
            daysUntilPasswordExpires: $data->daysUntilPasswordExpires,
        );
    }
}
