<?php

namespace Modules\Shared\Exceptions;

use Exception;

class CircuitBreakerOpenException extends Exception
{
    public function __construct(
        public readonly string $serviceName,
        public readonly int $failureCount,
        public readonly int $recoveryTimeout,
    ) {
        parent::__construct(
            "Circuit Breaker OPEN for service '{$serviceName}'. Failures: {$failureCount}. Retry after {$recoveryTimeout}s."
        );
    }
}
