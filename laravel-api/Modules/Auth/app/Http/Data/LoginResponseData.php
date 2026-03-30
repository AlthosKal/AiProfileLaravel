<?php

namespace Modules\Auth\Http\Data;

/**
 * DTO que encapsula el resultado de un login exitoso.
 *
 * Además de confirmar la autenticación, comunica al frontend
 * el estado post-login del usuario mediante claves semánticas,
 * para que pueda redirigir o mostrar advertencias según corresponda:
 *
 *   - twoFactorRequired:          redirigir al desafío 2FA
 *   - emailVerificationRequired:  redirigir a verificación de email
 *   - passwordExpiringSoon:       mostrar advertencia con días restantes
 */
final readonly class LoginResponseData
{
    public function __construct(
        public bool $twoFactorRequired,
        public bool $emailVerificationRequired,
        public bool $passwordExpiringSoon,
        public ?int $daysUntilPasswordExpires,
    ) {}
}
