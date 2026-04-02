<?php

namespace Modules\Shared\Actions;

use Modules\Shared\Enums\CircuitBreakerStatus;
use Modules\Shared\Exceptions\CircuitBreakerOpenException;
use Modules\Shared\Interfaces\CircuitBreakerStoreInterface;
use Modules\Shared\Stores\CircuitBreakerStore;
use Throwable;

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
 *
 * Configuración vía .env:
 * - CIRCUIT_BREAKER_FAILURE_THRESHOLD
 * - CIRCUIT_BREAKER_RECOVERY_TIMEOUT
 * - CIRCUIT_BREAKER_SUCCESS_THRESHOLD
 */
class CircuitBreakerAction
{
    private CircuitBreakerStoreInterface $store;

    public function __construct(
        public readonly string $serviceName,
        ?CircuitBreakerStoreInterface $store = null,
    ) {
        $this->store = $store ?? new CircuitBreakerStore($serviceName);
    }

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
        $state = $this->store->getState();

        if ($state === CircuitBreakerStatus::STATE_OPEN) {
            if (! $this->store->shouldAttemptRecovery()) {
                report(new CircuitBreakerOpenException(
                    serviceName: $this->serviceName,
                    failureCount: $this->store->getFailureCount(),
                    recoveryTimeout: config('app.circuit_breaker.recovery_timeout'),
                ));

                return $fallback();
            }

            $this->store->transitionToHalfOpen();
        }

        try {
            $result = $operation();
        } catch (Throwable $e) {
            report($e);
            $this->store->recordFailure($e);

            return $fallback();
        }

        $this->store->recordSuccess();

        return $result;
    }

    /**
     * Verificar si el circuit está disponible para operaciones.
     */
    public function isAvailable(): bool
    {
        $state = $this->store->getState();

        return $state === CircuitBreakerStatus::STATE_CLOSED
            || ($state === CircuitBreakerStatus::STATE_OPEN && $this->store->shouldAttemptRecovery());
    }

    /**
     * Reset manual del circuit (útil para admin/testing).
     */
    public function reset(): void
    {
        $this->store->reset();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->store->getMetrics();
    }
}
