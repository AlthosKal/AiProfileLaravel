<?php

namespace Modules\Shared\Interfaces;

use Modules\Shared\Enums\CircuitBreakerStatus;
use Throwable;

interface CircuitBreakerStoreInterface
{
    public function getState(): CircuitBreakerStatus;

    public function setState(CircuitBreakerStatus $state): void;

    public function recordFailure(Throwable $e): void;

    public function recordSuccess(): void;

    public function transitionToHalfOpen(): void;

    public function shouldAttemptRecovery(): bool;

    public function getFailureCount(): int;

    public function getSuccessCount(): int;

    public function reset(): void;

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array;
}
