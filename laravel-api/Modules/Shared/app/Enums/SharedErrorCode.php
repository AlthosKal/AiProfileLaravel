<?php

namespace Modules\Shared\Enums;

enum SharedErrorCode: string
{
    case BaseError = 'base_error';
    case CircuitBreakerOpen = 'circuit_breaker_open';
}
