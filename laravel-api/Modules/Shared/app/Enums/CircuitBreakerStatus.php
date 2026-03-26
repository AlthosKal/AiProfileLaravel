<?php

namespace Modules\Shared\Enums;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Shared\Events\CircuitBreakerClosed;
use Modules\Shared\Events\CircuitBreakerOpened;

enum CircuitBreakerStatus: string
{
    case STATE_CLOSED = 'closed';
    case STATE_OPEN = 'open';
    case STATE_HALF_OPEN = 'half_open';

    /**
     * Obtener estado actual del circuit.
     */
    public function getState(string $serviceName): self
    {
        $value = Cache::get($this->stateKey($serviceName), self::STATE_CLOSED->value);

        return self::from($value);
    }

    /**
     * Establecer estado del circuit.
     */
    public function setState(self $state, string $serviceName): void
    {
        Cache::put($this->stateKey($serviceName), $state->value, now()->addMinutes(10));
    }

    /**
     * Registrar fallo en el servicio.
     */
    public function recordFailure(\Throwable $e, string $serviceName, int $failureThreshold, int $recoveryTimeout): void
    {
        $failures = $this->getFailureCount($serviceName) + 1;
        Cache::put($this->failuresKey($serviceName), $failures, now()->addMinutes(5));

        Log::error("Cortacircuitos: Proceso Fallido en {$serviceName}", [
            'failures' => $failures,
            'threshold' => $failureThreshold,
            'exception' => $e->getMessage(),
            'exception_class' => $e::class,
        ]);

        if ($failures >= $failureThreshold) {
            $this->open($serviceName, $failureThreshold, $recoveryTimeout);
        }
    }

    /**
     * Registrar éxito en el servicio.
     */
    public function recordSuccess(string $serviceName, int $successThreshold): void
    {
        $state = $this->getState($serviceName);

        if ($state === self::STATE_HALF_OPEN) {
            $successes = $this->getSuccessCount($serviceName) + 1;
            Cache::put($this->successesKey($serviceName), $successes, now()->addMinutes(2));

            Log::info("Cortacircuitos: Proceso exitoso en el estado semi-abierto para {$serviceName}", [
                'successes' => $successes,
                'threshold' => $successThreshold,
            ]);

            if ($successes >= $successThreshold) {
                $this->close($serviceName);
            }
        } elseif ($state === self::STATE_CLOSED) {
            Cache::forget($this->failuresKey($serviceName));
        }
    }

    /**
     * Abrir circuit breaker.
     */
    private function open(string $serviceName, int $failureThreshold, int $recoveryTimeout): void
    {
        $this->setState(self::STATE_OPEN, $serviceName);
        Cache::put($this->openedAtKey($serviceName), now()->timestamp, now()->addMinutes(10));

        Log::warning("Cortacircuitos: Circuito ABIERTO para {$serviceName}", [
            'failures' => $this->getFailureCount($serviceName),
            'recovery_timeout' => $recoveryTimeout,
        ]);

        event(new CircuitBreakerOpened(
            serviceName: $serviceName,
            failureCount: $this->getFailureCount($serviceName),
            failureThreshold: $failureThreshold,
            recoveryTimeout: $recoveryTimeout,
        ));
    }

    /**
     * Cerrar circuit breaker.
     */
    private function close(string $serviceName): void
    {
        $previousState = $this->getState($serviceName);

        $this->setState(self::STATE_CLOSED, $serviceName);
        Cache::forget($this->failuresKey($serviceName));
        Cache::forget($this->successesKey($serviceName));
        Cache::forget($this->openedAtKey($serviceName));

        Log::info("Cortacircuitos: Circuito CERRADO para {$serviceName}", [
            'previous_state' => $previousState->value,
        ]);

        event(new CircuitBreakerClosed(
            serviceName: $serviceName,
            previousState: $previousState->value,
        ));
    }

    /**
     * Verificar si debe intentar recuperación.
     */
    public function shouldAttemptRecovery(string $serviceName, int $recoveryTimeout): bool
    {
        $openedAt = Cache::get($this->openedAtKey($serviceName));

        if (! $openedAt) {
            return true;
        }

        return (now()->timestamp - $openedAt) >= $recoveryTimeout;
    }

    /**
     * Obtener contador de fallos.
     */
    public function getFailureCount(string $serviceName): int
    {
        return (int) Cache::get($this->failuresKey($serviceName), 0);
    }

    /**
     * Obtener contador de éxitos.
     */
    public function getSuccessCount(string $serviceName): int
    {
        return (int) Cache::get($this->successesKey($serviceName), 0);
    }

    /**
     * Resetear todos los contadores del circuit (útil para admin/testing).
     */
    public function reset(string $serviceName): void
    {
        Cache::forget($this->stateKey($serviceName));
        Cache::forget($this->failuresKey($serviceName));
        Cache::forget($this->successesKey($serviceName));
        Cache::forget($this->openedAtKey($serviceName));

        Log::info("Cortacircuitos: Reset manual para {$serviceName}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(string $serviceName, int $failureThreshold, int $recoveryTimeout, int $successThreshold): array
    {
        $state = $this->getState($serviceName);
        $openedAt = Cache::get($this->openedAtKey($serviceName));

        return [
            'service' => $serviceName,
            'state' => $state->value,
            'failures' => $this->getFailureCount($serviceName),
            'successes' => $this->getSuccessCount($serviceName),
            'opened_at' => $openedAt,
            'opened_at_human' => $openedAt ? now()->createFromTimestamp($openedAt)->diffForHumans() : null,
            'can_attempt_recovery' => $state === self::STATE_OPEN ? $this->shouldAttemptRecovery($serviceName, $recoveryTimeout) : null,
            'configuration' => [
                'failure_threshold' => $failureThreshold,
                'recovery_timeout' => $recoveryTimeout,
                'success_threshold' => $successThreshold,
            ],
        ];
    }

    private function stateKey(string $serviceName): string
    {
        return "circuit_breaker:{$serviceName}:state";
    }

    private function failuresKey(string $serviceName): string
    {
        return "circuit_breaker:{$serviceName}:failures";
    }

    private function successesKey(string $serviceName): string
    {
        return "circuit_breaker:{$serviceName}:successes";
    }

    private function openedAtKey(string $serviceName): string
    {
        return "circuit_breaker:{$serviceName}:opened_at";
    }
}
