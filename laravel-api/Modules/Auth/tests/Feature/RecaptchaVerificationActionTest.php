<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Auth\Actions\Auth\RecaptchaVerificationAction;
use Modules\Auth\Exceptions\RecaptchaVerificationException;
use Modules\Auth\Rules\RecaptchaV3Rule;
use Modules\Shared\Actions\CircuitBreakerAction;
use Modules\Shared\Enums\CircuitBreakerStatus;

const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
const RECAPTCHA_ACTION = 'login';
const RECAPTCHA_TOKEN = 'valid-token';

function makeRecaptchaAction(?CircuitBreakerAction $circuitBreaker = null): RecaptchaVerificationAction
{
    return new RecaptchaVerificationAction($circuitBreaker);
}

function openCircuitBreaker(string $serviceName): void
{
    Cache::put("circuit_breaker:$serviceName:state", CircuitBreakerStatus::STATE_OPEN->value, 600);
    Cache::put("circuit_breaker:$serviceName:opened_at", now()->timestamp, 600);
    Cache::put("circuit_breaker:$serviceName:failures", 5, 600);
}

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

describe('verify con Google exitoso', function () {
    it('retorna success=true cuando el score supera el mínimo', function () {
        Http::fake([
            RECAPTCHA_VERIFY_URL => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => RECAPTCHA_ACTION,
            ]),
        ]);

        $result = makeRecaptchaAction()->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        expect($result)->toMatchArray([
            'success' => true,
            'score' => 0.9,
            'action' => RECAPTCHA_ACTION,
            'fallback_used' => false,
        ]);
    });

    it('retorna success=false cuando el score no supera el mínimo', function () {
        Http::fake([
            RECAPTCHA_VERIFY_URL => Http::response([
                'success' => true,
                'score' => 0.1,
                'action' => RECAPTCHA_ACTION,
            ]),
        ]);

        $result = makeRecaptchaAction()->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        expect($result['success'])->toBeFalse()
            ->and($result['score'])->toBe(0.1)
            ->and($result['fallback_used'])->toBeFalse();
    });
});

describe('verify con Google fallando', function () {
    /**
     * Cuando verifyWithGoogle lanza una excepción, el CircuitBreakerAction la
     * captura con report() y ejecuta el fallback. Por eso se espera fallback_used=true
     * en lugar de que la excepción se propague al llamador.
     */
    it('usa el fallback cuando Google rechaza el token', function () {
        Http::fake([
            RECAPTCHA_VERIFY_URL => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $result = makeRecaptchaAction()->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        expect($result['fallback_used'])->toBeTrue();
    });

    it('usa el fallback cuando la acción no coincide', function () {
        Http::fake([
            RECAPTCHA_VERIFY_URL => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'register',
            ]),
        ]);

        $result = makeRecaptchaAction()->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        expect($result['fallback_used'])->toBeTrue();
    });

    it('usa el fallback cuando la API retorna error HTTP', function () {
        Http::fake([
            RECAPTCHA_VERIFY_URL => Http::response([], 500),
        ]);

        $result = makeRecaptchaAction()->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        expect($result['fallback_used'])->toBeTrue();
    });
});

describe('verify con fallback', function () {
    beforeEach(function () {
        openCircuitBreaker('recaptcha');
    });

    it('usa el fallback cuando el circuit está abierto', function () {
        $result = makeRecaptchaAction()->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        expect($result)->toMatchArray([
            'success' => true,
            'score' => 0.0,
            'action' => RECAPTCHA_ACTION,
            'fallback_used' => true,
        ]);
    });

    it('el fallback no realiza llamadas HTTP a Google', function () {
        Http::fake();

        makeRecaptchaAction()->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        Http::assertNothingSent();
    });

    it('usa el fallback con circuit inyectado abierto', function () {
        $circuitBreaker = new CircuitBreakerAction('recaptcha-test');
        openCircuitBreaker('recaptcha-test');

        $result = makeRecaptchaAction($circuitBreaker)->verify(RECAPTCHA_TOKEN, RECAPTCHA_ACTION);

        expect($result['fallback_used'])->toBeTrue();
    });
});

describe('RecaptchaV3Rule', function () {
    it('falla cuando el token está vacío', function () {
        Http::fake();

        $rule = new RecaptchaV3Rule('login');
        $failed = false;

        $rule->validate('recaptcha_token', '', function (string $message, ?string $translation = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, app('translator'));
        });

        expect($failed)->toBeTrue();
        Http::assertNothingSent();
    });

    it('falla cuando el token no es string', function () {
        Http::fake();

        $rule = new RecaptchaV3Rule('login');
        $failed = false;

        $rule->validate('recaptcha_token', null, function (string $message, ?string $translation = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, app('translator'));
        });

        expect($failed)->toBeTrue();
        Http::assertNothingSent();
    });

    it('falla cuando Google retorna score bajo', function () {
        Http::fake([
            RECAPTCHA_VERIFY_URL => Http::response([
                'success' => true,
                'score' => 0.1,
                'action' => 'login',
            ]),
        ]);

        $rule = new RecaptchaV3Rule('login');
        $failed = false;

        $rule->validate('recaptcha_token', RECAPTCHA_TOKEN, function (string $message, ?string $translation = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, app('translator'));
        });

        expect($failed)->toBeTrue();
    });

    it('pasa cuando Google retorna score suficiente', function () {
        Http::fake([
            RECAPTCHA_VERIFY_URL => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'login',
            ]),
        ]);

        $rule = new RecaptchaV3Rule('login');
        $failed = false;

        $rule->validate('recaptcha_token', RECAPTCHA_TOKEN, function (string $message, ?string $translation = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, app('translator'));
        });

        expect($failed)->toBeFalse();
    });

    it('pasa cuando el circuit está abierto y usa fallback', function () {
        openCircuitBreaker('recaptcha');

        $rule = new RecaptchaV3Rule('login');
        $failed = false;

        $rule->validate('recaptcha_token', RECAPTCHA_TOKEN, function (string $message, ?string $translation = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, app('translator'));
        });

        expect($failed)->toBeFalse();
    });
});

describe('RecaptchaVerificationException', function () {
    it('tiene el código HTTP 503', function () {
        $exception = new RecaptchaVerificationException(
            message: 'Error de verificación',
            details: ['token' => RECAPTCHA_TOKEN],
        );

        expect($exception->getCode())->toBe(503)
            ->and($exception->getMessage())->toBe('Error de verificación')
            ->and($exception->getDetails())->toBe(['token' => RECAPTCHA_TOKEN])
            ->and($exception->getErrorCode())->toBe('recaptcha_verification_failed');
    });

    it('render retorna JSON con la estructura correcta', function () {
        $exception = new RecaptchaVerificationException(
            message: 'Error de verificación',
            details: ['reason' => 'timeout'],
        );

        $response = $exception->render();

        expect($response->getStatusCode())->toBe(503)
            ->and($response->getData(assoc: true))->toMatchArray([
                'error' => 'recaptcha_verification_failed',
                'message' => 'Error de verificación',
                'details' => ['reason' => 'timeout'],
            ]);
    });
});
