<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\Shared\Actions\CircuitBreakerAction;
use Modules\Shared\Enums\CircuitBreakerStatus;
use Modules\Shared\Events\CircuitBreakerClosed;
use Modules\Shared\Events\CircuitBreakerHalfOpen;
use Modules\Shared\Events\CircuitBreakerOpened;
use Modules\Shared\Exceptions\CircuitBreakerOpenException;
use Modules\Shared\Stores\CircuitBreakerStore;

const SERVICE = 'test-service';

function makeAction(): CircuitBreakerAction
{
    return new CircuitBreakerAction(serviceName: SERVICE);
}

function store(): CircuitBreakerStore
{
    return new CircuitBreakerStore(SERVICE);
}

function failureThreshold(): int
{
    return config('app.circuit_breaker.failure_threshold');
}

function recoveryTimeout(): int
{
    return config('app.circuit_breaker.recovery_timeout');
}

function successThreshold(): int
{
    return config('app.circuit_breaker.success_threshold');
}

beforeEach(function () {
    Cache::flush();
    Event::fake();
});

describe('estado CLOSED', function () {
    it('ejecuta la operación y retorna su resultado', function () {
        $result = makeAction()->call(
            fn () => 'ok',
            fn () => 'fallback',
        );

        expect($result)->toBe('ok');
    });

    it('ejecuta el fallback y registra el fallo cuando la operación lanza excepción', function () {
        $result = makeAction()->call(
            fn () => throw new RuntimeException('fallo'),
            fn () => 'fallback',
        );

        expect($result)->toBe('fallback');
        expect(store()->getFailureCount())->toBe(1);
    });

    it('abre el circuit después de alcanzar el threshold de fallos', function () {
        $action = makeAction();

        foreach (range(1, failureThreshold()) as $i) {
            $action->call(fn () => throw new RuntimeException, fn () => 'fb');
        }

        expect(store()->getState())->toBe(CircuitBreakerStatus::STATE_OPEN);
        Event::assertDispatched(CircuitBreakerOpened::class, fn ($e) => $e->serviceName === SERVICE);
    });

    it('resetea el contador de fallos tras un éxito', function () {
        $action = makeAction();

        $action->call(fn () => throw new RuntimeException, fn () => 'fb');
        expect(store()->getFailureCount())->toBe(1);

        $action->call(fn () => 'ok', fn () => 'fb');
        expect(store()->getFailureCount())->toBe(0);
    });
});

describe('estado OPEN', function () {
    beforeEach(function () {
        Cache::put('circuit_breaker:'.SERVICE.':state', CircuitBreakerStatus::STATE_OPEN->value, 600);
        Cache::put('circuit_breaker:'.SERVICE.':failures', failureThreshold(), 600);
        Cache::put('circuit_breaker:'.SERVICE.':opened_at', now()->timestamp, 600);
    });

    it('usa el fallback directamente sin ejecutar la operación', function () {
        $operationCalled = false;

        $result = makeAction()->call(
            function () use (&$operationCalled) {
                $operationCalled = true;

                return 'op';
            },
            fn () => 'fallback',
        );

        expect($result)->toBe('fallback');
        expect($operationCalled)->toBeFalse();
    });

    it('transiciona a HALF_OPEN cuando el timeout de recuperación expira', function () {
        Cache::put('circuit_breaker:'.SERVICE.':opened_at', now()->subSeconds(recoveryTimeout() + 1)->timestamp, 600);

        makeAction()->call(fn () => 'ok', fn () => 'fb');

        Event::assertDispatched(CircuitBreakerHalfOpen::class, fn ($e) => $e->serviceName === SERVICE);
    });
});

describe('estado HALF_OPEN', function () {
    beforeEach(function () {
        Cache::put('circuit_breaker:'.SERVICE.':state', CircuitBreakerStatus::STATE_HALF_OPEN->value, 600);
        Cache::put('circuit_breaker:'.SERVICE.':opened_at', now()->subSeconds(recoveryTimeout() + 1)->timestamp, 600);
    });

    it('cierra el circuit tras alcanzar el threshold de éxitos', function () {
        $action = makeAction();

        foreach (range(1, successThreshold()) as $i) {
            $action->call(fn () => 'ok', fn () => 'fb');
        }

        expect(store()->getState())->toBe(CircuitBreakerStatus::STATE_CLOSED);
        Event::assertDispatched(CircuitBreakerClosed::class, fn ($e) => $e->serviceName === SERVICE);
    });

    it('reabre el circuit si alcanza el threshold de fallos en HALF_OPEN', function () {
        $action = makeAction();

        foreach (range(1, failureThreshold()) as $i) {
            $action->call(fn () => throw new RuntimeException, fn () => 'fb');
        }

        expect(store()->getState())->toBe(CircuitBreakerStatus::STATE_OPEN);
    });
});

describe('utilidades', function () {
    it('isAvailable retorna true cuando el circuit está CLOSED', function () {
        expect(makeAction()->isAvailable())->toBeTrue();
    });

    it('isAvailable retorna false cuando el circuit está OPEN y no expiró el timeout', function () {
        Cache::put('circuit_breaker:'.SERVICE.':state', CircuitBreakerStatus::STATE_OPEN->value, 600);
        Cache::put('circuit_breaker:'.SERVICE.':opened_at', now()->timestamp, 600);

        expect(makeAction()->isAvailable())->toBeFalse();
    });

    it('isAvailable retorna true cuando el circuit está OPEN pero expiró el timeout', function () {
        Cache::put('circuit_breaker:'.SERVICE.':state', CircuitBreakerStatus::STATE_OPEN->value, 600);
        Cache::put('circuit_breaker:'.SERVICE.':opened_at', now()->subSeconds(recoveryTimeout() + 1)->timestamp, 600);

        expect(makeAction()->isAvailable())->toBeTrue();
    });

    it('reset limpia todos los contadores del circuit', function () {
        Cache::put('circuit_breaker:'.SERVICE.':state', CircuitBreakerStatus::STATE_OPEN->value, 600);
        Cache::put('circuit_breaker:'.SERVICE.':failures', 5, 600);

        makeAction()->reset();

        expect(store()->getState())->toBe(CircuitBreakerStatus::STATE_CLOSED);
        expect(store()->getFailureCount())->toBe(0);
    });

    it('getMetrics retorna la estructura correcta', function () {
        $metrics = makeAction()->getMetrics();

        expect($metrics)
            ->toHaveKeys(['service', 'state', 'failures', 'successes', 'opened_at', 'configuration'])
            ->and($metrics['service'])->toBe(SERVICE)
            ->and($metrics['configuration'])->toMatchArray([
                'failure_threshold' => failureThreshold(),
                'recovery_timeout' => recoveryTimeout(),
                'success_threshold' => successThreshold(),
            ]);
    });

    it('CircuitBreakerOpenException contiene el mensaje correcto', function () {
        $e = new CircuitBreakerOpenException(
            serviceName: SERVICE,
            failureCount: failureThreshold(),
            recoveryTimeout: recoveryTimeout(),
        );

        expect($e->getMessage())->toContain(SERVICE)
            ->and($e->serviceName)->toBe(SERVICE)
            ->and($e->failureCount)->toBe(failureThreshold())
            ->and($e->recoveryTimeout)->toBe(recoveryTimeout());
    });
});
