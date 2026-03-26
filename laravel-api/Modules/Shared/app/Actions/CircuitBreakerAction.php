<?php

namespace Modules\Shared\Actions;

use Illuminate\Support\Facades\Log;
use Modules\Shared\Enums\CircuitBreakerStatus;
use Modules\Shared\Events\CircuitBreakerHalfOpen;
use Modules\Shared\Exceptions\CircuitBreakerOpenException;

/**
 * Circuit Breaker Pattern Implementation
 *
 * Previene cascadas de fallos y mejora la resiliencia del sistema
 * al detener temporalmente llamadas a servicios que están fallando.
 *
 * Estados:
 * - CLOSED: Operación normal, todas las llamadas se ejecutan
 * - OPEN: Servicio fallando, usar fallback inmediatamente
 * - HALF_OPEN: Intentando recuperación, probar servicio gradualmente
 */
readonly class CircuitBreakerAction
{
    /**
     * @param  string  $serviceName  Nombre único del servicio
     * @param  int  $failureThreshold  Número de fallos antes de abrir el circuit
     * @param  int  $recoveryTimeout  Segundos antes de intentar recuperación
     * @param  int  $successThreshold  Éxitos necesarios para cerrar desde half-open
     */
    public function __construct(
        private CircuitBreakerStatus $status,
        private string $serviceName,
        private int $failureThreshold = 3,
        private int $recoveryTimeout = 60,
        private int $successThreshold = 2,
    ) {}

    /**
     * Ejecutar operación con circuit breaker.
     *
     * Si la operación falla, el error se reporta al manejador global de excepciones
     * y se ejecuta el fallback. No se relanza la excepción.
     *
     * @param  callable(): mixed  $operation  Operación principal a ejecutar
     * @param  callable(): mixed  $fallback  Operación de fallback
     * @return mixed Resultado de la operación o del fallback
     */
    public function call(callable $operation, callable $fallback): mixed
    {
        $state = $this->status->getState($this->serviceName);

        if ($state === CircuitBreakerStatus::STATE_OPEN) {
            if (! $this->status->shouldAttemptRecovery($this->serviceName, $this->recoveryTimeout)) {
                Log::warning("Cortacircuitos: Usando mecanismo de respaldo para {$this->serviceName} (el circuito está ABIERTO)");

                report(new CircuitBreakerOpenException(
                    serviceName: $this->serviceName,
                    failureCount: $this->status->getFailureCount($this->serviceName),
                    recoveryTimeout: $this->recoveryTimeout,
                ));

                return $fallback();
            }

            $this->status->setState(CircuitBreakerStatus::STATE_HALF_OPEN, $this->serviceName);
            Log::info("Cortacircuitos: Intentando la recuperación de {$this->serviceName}");

            event(new CircuitBreakerHalfOpen(
                serviceName: $this->serviceName,
                successThreshold: $this->successThreshold,
            ));
        }

        try {
            $result = $operation();
        } catch (\Throwable $e) {
            report($e);
            $this->status->recordFailure($e, $this->serviceName, $this->failureThreshold, $this->recoveryTimeout);

            return $fallback();
        }

        $this->status->recordSuccess($this->serviceName, $this->successThreshold);

        return $result;
    }

    /**
     * Verificar si el circuit está disponible para operaciones.
     */
    public function isAvailable(): bool
    {
        $state = $this->status->getState($this->serviceName);

        return $state === CircuitBreakerStatus::STATE_CLOSED
            || ($state === CircuitBreakerStatus::STATE_OPEN && $this->status->shouldAttemptRecovery($this->serviceName, $this->recoveryTimeout));
    }

    /**
     * Reset manual del circuit (útil para admin/testing).
     */
    public function reset(): void
    {
        $this->status->reset($this->serviceName);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->status->getMetrics(
            serviceName: $this->serviceName,
            failureThreshold: $this->failureThreshold,
            recoveryTimeout: $this->recoveryTimeout,
            successThreshold: $this->successThreshold,
        );
    }
}
