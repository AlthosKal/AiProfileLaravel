<?php

namespace Modules\Shared\Enums;

enum SharedErrorCode: string
{
    case BaseError = 'base_error';
    case CircuitBreakerOpen = 'circuit_breaker_open';
    case RateLimiterForAppForIdFired = 'rate_limiter_for_id_fired';
    case RateLimiterForAppForEmailFired = 'rate_limiter_for_email_fired';
    case PromptInjectionDetected = 'prompt_injection_detected';
}
