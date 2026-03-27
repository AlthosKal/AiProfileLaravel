<?php

namespace Modules\Auth\Enums;

enum AuthErrorCode: string
{
    case RecaptchaVerificationFailed = 'recaptcha_verification_failed';
    case CaptchaVerificationRequired = 'captcha_verification_required';
    case CaptchaVerificationFailed = 'captcha_verification_failed';
    case LoginFailed = 'auth_failed';
    case LoginThrottled = 'auth_throttled';
}
