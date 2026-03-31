<?php

namespace Modules\Auth\Enums;

/**
 * Claves semánticas para estados informativos post-login del módulo Auth.
 *
 * No son errores ni éxitos: indican condiciones pendientes que el frontend
 * debe evaluar para redirigir o mostrar advertencias al usuario.
 */
enum AuthStatusCode: string
{
    case TwoFactorRequired = 'two_factor_required';
    case EmailVerificationRequired = 'email_verification_required';
    case PasswordExpiringSoon = 'password_expiring_soon';
}
