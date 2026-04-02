<?php

namespace Modules\Shared\Stores;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Shared\Enums\CircuitBreakerStatus;
use Modules\Shared\Events\CircuitBreakerClosedEvent;
use Modules\Shared\Events\CircuitBreakerHalfOpenEvent;
use Modules\Shared\Events\CircuitBreakerOpenedEvent;
use Modules\Shared\Interfaces\CircuitBreakerStoreInterface;
use Throwable;

readonly class CircuitBreakerStore implements CircuitBreakerStoreInterface
{
    private int $failureThreshold;

    private int $recoveryTimeout;

    private int $successThreshold;

    public function __construct(private string $serviceName)
    {
        $this->failureThreshold = config('app.circuit_breaker.failure_threshold');
        $this->recoveryTimeout = config('app.circuit_breaker.recovery_timeout');
        $this->successThreshold = config('app.circuit_breaker.success_threshold');
    }

    public function getState(): CircuitBreakerStatus
    {
        return CircuitBreakerStatus::from(
            Cache::get($this->stateKey(), CircuitBreakerStatus::STATE_CLOSED->value)
        );
    }

    public function setState(CircuitBreakerStatus $state): void
    {
        Cache::put($this->stateKey(), $state->value, now()->addMinutes(10));
    }

    public function recordFailure(Throwable $e): void
    {
        $failures = $this->getFailureCount() + 1;
        Cache::put($this->failuresKey(), $failures, now()->addMinutes(5));

        Log::error("Cortacircuitos: Proceso fallido en $this->serviceName", [
            'failures' => $failures,
            'threshold' => $this->failureThreshold,
            'exception' => $e->getMessage(),
            'exception_class' => $e::class,
        ]);

        if ($failures >= $this->failureThreshold) {
            $this->open();
        }
    }

    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === CircuitBreakerStatus::STATE_HALF_OPEN) {
            $successes = $this->getSuccessCount() + 1;
            Cache::put($this->successesKey(), $successes, now()->addMinutes(2));

            Log::info("Cortacircuitos: Proceso exitoso en estado semi-abierto para $this->serviceName", [
                'successes' => $successes,
                'threshold' => $this->successThreshold,
            ]);

            if ($successes >= $this->successThreshold) {
                $this->close();
            }
        } elseif ($state === CircuitBreakerStatus::STATE_CLOSED) {
            Cache::forget($this->failuresKey());
        }
    }

    public function transitionToHalfOpen(): void
    {
        $this->setState(CircuitBreakerStatus::STATE_HALF_OPEN);

        Log::info("Cortacircuitos: Intentando la recuperación de $this->serviceName");

        event(new CircuitBreakerHalfOpenEvent(
            serviceName: $this->serviceName,
            successThreshold: $this->successThreshold,
        ));
    }

    public function shouldAttemptRecovery(): bool
    {
        $openedAt = Cache::get($this->openedAtKey());

        if (! $openedAt) {
            return true;
        }

        return (now()->timestamp - $openedAt) >= $this->recoveryTimeout;
    }

    public function getFailureCount(): int
    {
        return (int) Cache::get($this->failuresKey(), 0);
    }

    public function getSuccessCount(): int
    {
        return (int) Cache::get($this->successesKey(), 0);
    }

    public function reset(): void
    {
        Cache::forget($this->stateKey());
        Cache::forget($this->failuresKey());
        Cache::forget($this->successesKey());
        Cache::forget($this->openedAtKey());

        Log::info("Cortacircuitos: Reset manual para $this->serviceName");
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $state = $this->getState();
        $openedAt = Cache::get($this->openedAtKey());

        return [
            'service' => $this->serviceName,
            'state' => $state->value,
            'failures' => $this->getFailureCount(),
            'successes' => $this->getSuccessCount(),
            'opened_at' => $openedAt,
            'opened_at_human' => $openedAt ? now()->createFromTimestamp($openedAt)->diffForHumans() : null,
            'can_attempt_recovery' => $state === CircuitBreakerStatus::STATE_OPEN ? $this->shouldAttemptRecovery() : null,
            'configuration' => [
                'failure_threshold' => $this->failureThreshold,
                'recovery_timeout' => $this->recoveryTimeout,
                'success_threshold' => $this->successThreshold,
            ],
        ];
    }

    private function open(): void
    {
        $this->setState(CircuitBreakerStatus::STATE_OPEN);
        Cache::put($this->openedAtKey(), now()->timestamp, now()->addMinutes(10));

        Log::warning("Cortacircuitos: Circuito ABIERTO para $this->serviceName", [
            'failures' => $this->getFailureCount(),
            'recovery_timeout' => $this->recoveryTimeout,
        ]);

        event(new CircuitBreakerOpenedEvent(
            serviceName: $this->serviceName,
            failureCount: $this->getFailureCount(),
            failureThreshold: $this->failureThreshold,
            recoveryTimeout: $this->recoveryTimeout,
        ));
    }

    private function close(): void
    {
        $previousState = $this->getState();

        $this->setState(CircuitBreakerStatus::STATE_CLOSED);
        Cache::forget($this->failuresKey());
        Cache::forget($this->successesKey());
        Cache::forget($this->openedAtKey());

        Log::info("Cortacircuitos: Circuito CERRADO para $this->serviceName", [
            'previous_state' => $previousState->value,
        ]);

        event(new CircuitBreakerClosedEvent(
            serviceName: $this->serviceName,
            previousState: $previousState->value,
        ));
    }

    private function stateKey(): string
    {
        return "circuit_breaker:$this->serviceName:state";
    }

    private function failuresKey(): string
    {
        return "circuit_breaker:$this->serviceName:failures";
    }

    private function successesKey(): string
    {
        return "circuit_breaker:$this->serviceName:successes";
    }

    private function openedAtKey(): string
    {
        return "circuit_breaker:$this->serviceName:opened_at";
    }
}
