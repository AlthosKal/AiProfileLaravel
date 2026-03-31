<?php

namespace Modules\Shared\Exceptions;

use Modules\Shared\Enums\SharedErrorCode;

class CircuitBreakerOpenException extends BaseException
{
    public function __construct(
        public readonly string $serviceName,
        public readonly int $failureCount,
        public readonly int $recoveryTimeout,
    ) {
        parent::__construct(
            errorCode: SharedErrorCode::CircuitBreakerOpen,
        );
    }
}
