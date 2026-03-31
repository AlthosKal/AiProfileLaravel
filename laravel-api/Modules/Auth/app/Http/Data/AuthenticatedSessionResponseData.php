<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthStatusCode;

/**
 * DTO que encapsula la respuesta JSON del endpoint de login.
 *
 * Expone el token Sanctum y el estado post-login con claves semánticas
 * para que el frontend pueda reaccionar de manera apropiada:
 *
 *   - token:                  Bearer token para incluir en Authorization header
 *   - twoFactorCode:          redirigir al desafío 2FA
 *   - emailVerificationCode:  redirigir a verificación de email
 *   - passwordExpirationCode: mostrar advertencia de contraseña próxima a vencer
 */
final readonly class AuthenticatedSessionResponseData
{
    public function __construct(
        public string $token,
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
            token: $data->token,
            twoFactorCode: $data->twoFactorRequired ? AuthStatusCode::TwoFactorRequired->value : null,
            emailVerificationCode: $data->emailVerificationRequired ? AuthStatusCode::EmailVerificationRequired->value : null,
            passwordExpirationCode: $data->passwordExpiringSoon ? AuthStatusCode::PasswordExpiringSoon->value : null,
            daysUntilPasswordExpires: $data->daysUntilPasswordExpires,
        );
    }
}
