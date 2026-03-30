<?php

namespace Modules\Auth\Enums;

/**
 * Enum donde se declaran las claves semanticas de API para las diferentes validaciónes de la API
 */
enum AuthErrorCode: string
{
    // reCAPTCHA
    case RecaptchaVerificationFailed = 'recaptcha_verification_failed';
    case CaptchaVerificationRequired = 'captcha_verification_required';
    case CaptchaVerificationFailed = 'captcha_verification_failed';

    // Login
    case LoginFailed = 'auth_failed';
    case LoginThrottled = 'auth_throttled';

    // Verificaciones post-login
    case TwoFactorRequired = 'two_factor_required';
    case EmailVerificationRequired = 'email_verification_required';
    case PasswordExpiringSoon = 'password_expiring_soon';

    // Lockout
    case FirstLockoutFired = 'first_lockout_fired';
    case SecondLockoutFired = 'second_lockout_fired';
    case ThirdLockoutFired = 'third_lockout_fired';
    case LockoutUserNotFound = 'lockout_user_not_found';

    // Password reset
    case PasswordResetLinkSent = 'password_reset_link_sent';
    case PasswordResetLinkFailed = 'password_reset_link_failed';
    case PasswordResetSuccess = 'password_reset_success';
    case PasswordResetFailed = 'password_reset_failed';

    // Validación de campos del Login
    case EmailRequired = 'email_required';
    case EmailInvalid = 'email_invalid';
    case EmailTooLong = 'email_too_long';
    case PasswordRequired = 'password_required';
    case RememberInvalidFormat = 'remember_invalid_format';
    case RecaptchaInvalidFormat = 'recaptcha_invalid_format';

    // Validación de campos del Reset de contraseña
    case TokenRequired = 'token_required';
    case PasswordConfirmationRequired = 'password_confirmation_required';
    case PasswordConfirmationMismatch = 'password_confirmation_mismatch';
}
