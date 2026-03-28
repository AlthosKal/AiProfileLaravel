<?php

namespace Modules\Auth\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Exceptions\RecaptchaVerificationException;
use Modules\Shared\Actions\CircuitBreakerAction;
use Throwable;

/**
 * Verifica tokens de reCAPTCHA v3 con Google API usando el patrón Circuit Breaker
 * para mejorar la resiliencia cuando el servicio de Google está caído.
 */
class RecaptchaVerificationAction
{
    public function __construct(
        private ?CircuitBreakerAction $circuitBreaker = null,
    ) {
        $this->circuitBreaker ??= new CircuitBreakerAction('recaptcha');
    }

    /**
     * Verificar token de reCAPTCHA con Circuit Breaker y Fallback.
     */
    public function verify(string $token, string $action): array
    {
        return $this->circuitBreaker->call(
            operation: fn () => $this->verifyWithGoogle($token, $action),
            fallback: fn () => $this->fallbackVerification($action),
        );
    }

    /**
     * Verificación real con Google reCAPTCHA API.
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws Throwable
     */
    private function verifyWithGoogle(string $token, string $action): array
    {
        $data = Http::timeout(config('recaptchav3.timeout_seconds'))
            ->asForm()
            ->post(config('recaptchav3.origin'), [
                'secret' => config('recaptchav3.secret'),
                'response' => $token,
            ])
            ->throw(fn () => throw new RecaptchaVerificationException(
                message: 'Google reCAPTCHA API no disponible',
            ))
            ->json();

        throw_if(
            ! ($data['success'] ?? false),
            new RecaptchaVerificationException(
                message: 'Verificación de reCAPTCHA rechazada por Google',
                details: ['error_codes' => $data['error-codes'] ?? []],
            )
        );

        throw_if(
            ($data['action'] ?? '') !== $action,
            new RecaptchaVerificationException(
                message: 'Acción de reCAPTCHA no coincide',
                details: ['expected' => $action, 'received' => $data['action'] ?? ''],
            )
        );

        $score = $data['score'] ?? 0.0;

        return [
            'success' => $score >= (float) config('recaptchav3.min_score'),
            'score' => $score,
            'action' => $action,
            'fallback_used' => false,
        ];
    }

    /**
     * Verificación de fallback cuando Google no está disponible.
     *
     * Estrategia de degradación elegante (graceful degradation) que permite
     * que el sistema siga funcionando mientras reCAPTCHA está caído,
     * registrando todo para auditoría.
     */
    private function fallbackVerification(string $action): array
    {
        Log::warning('reCAPTCHA fallback activated', [
            'action' => $action,
            'circuit_metrics' => $this->circuitBreaker->getMetrics(),
        ]);

        return [
            'success' => true,
            'score' => 0.0,
            'action' => $action,
            'fallback_used' => true,
        ];
    }
}
