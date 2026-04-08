<?php

namespace Modules\Auth\Enums;

/**
 * Enum donde se declaran las claves semánticas de API para las diferentes validaciónes de la API
 */
enum AuthErrorCode: string
{
    // User
    case UserAlreadyExists = 'user_al_ready_exists';
    case UserNotFound = 'user_not_found';

    // Identification Type
    case IdentificationTypeInvalid = 'identification_type_invalid';

    // reCAPTCHA
    case RecaptchaVerificationFailed = 'recaptcha_verification_failed';
    case CaptchaVerificationRequired = 'captcha_verification_required';
    case CaptchaVerificationFailed = 'captcha_verification_failed';

    // Google Auth
    case GoogleAuthDisabled = 'google_auth_disabled';

    // Register
    case NameRequired = 'name_required';
    case NameString = 'name_string';
    case NameTooLong = 'name_too_long';
    case PasswordString = 'password_string';
    case PasswordTooShort = 'password_too_short';
    case IdentificationNumberRequired = 'identification_number_required';
    case IdentificationNumberInteger = 'identification_number_integer';
    case IdentificationNumberInvalidLength = 'identification_number_invalid_length';
    case IdentificationTypeRequired = 'identification_type_required';
    case IdentificationTypeString = 'identification_type_string';
    case IdentificationTypeTooLong = 'identification_type_too_long';

    // Login
    case LoginFailed = 'auth_failed';
    case LoginThrottled = 'auth_throttled';

    // Lockout
    case FirstLockoutFired = 'first_lockout_fired';
    case SecondLockoutFired = 'second_lockout_fired';
    case ThirdLockoutFired = 'third_lockout_fired';
    case LockoutUserNotFound = 'lockout_user_not_found';

    // Password reset
    case PasswordResetLinkFailed = 'password_reset_link_failed';
    case PasswordResetFailed = 'password_reset_failed';
    case PasswordInHistory = 'password_in_history';
    case PasswordExpired = 'password_expired';

    // Validación de campos del Login
    case EmailRequired = 'email_required';
    case EmailInvalid = 'email_invalid';
    case EmailTooLong = 'email_too_long';
    case PasswordRequired = 'password_required';
    case DeviceNameRequired = 'device_name_required';
    case DeviceNameTooLong = 'device_name_too_long';
    case RememberInvalidFormat = 'remember_invalid_format';
    case RecaptchaInvalidFormat = 'recaptcha_invalid_format';

    // Validación de campos del Reset de contraseña
    case TokenRequired = 'token_required';
    case PasswordConfirmationMismatch = 'password_confirmation_mismatch';
}
