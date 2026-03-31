<?php

namespace Modules\Auth\Http\Data;

/**
 * DTO que encapsula el resultado interno de un login exitoso.
 *
 * Transporta el token Sanctum generado y el estado post-login del usuario.
 * Es un DTO interno — no se serializa directamente a JSON, sino que se
 * transforma en AuthenticatedSessionResponseData para la respuesta al frontend.
 *
 *   - token:                      plainTextToken de Sanctum (solo disponible una vez)
 *   - twoFactorRequired:          redirigir al desafío 2FA
 *   - emailVerificationRequired:  redirigir a verificación de email
 *   - passwordExpiringSoon:       mostrar advertencia con días restantes
 */
final readonly class LoginResponseData
{
    public function __construct(
        public string $token,
        public bool $twoFactorRequired,
        public bool $emailVerificationRequired,
        public bool $passwordExpiringSoon,
        public ?int $daysUntilPasswordExpires,
    ) {}
}
