<?php

namespace Modules\Auth\Enums;

/**
 * Claves semánticas para respuestas de éxito del módulo Auth.
 */
enum AuthSuccessCode: string
{
    // Login
    case LoginSuccess = 'login_success';

    // Logout
    case LogoutSuccess = 'logout_success';

    // Email verification
    case VerificationLinkSent = 'verification_link_sent';

    // Password reset
    case PasswordResetLinkSent = 'password_reset_link_sent';
    case PasswordResetSuccess = 'password_reset_success';
}
