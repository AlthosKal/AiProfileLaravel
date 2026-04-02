<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Actions\Auth\LockoutStateAction;
use Modules\Auth\Actions\Auth\LoginAction;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Exceptions\LoginThrottledException;
use Modules\Auth\Helpers\CheckAccountBlockStatusHelper;
use Modules\Auth\Http\Data\LoginData;
use Modules\Auth\Models\User;
use Modules\Auth\Stores\LockoutStateStore;

uses(RefreshDatabase::class);

/**
 * Helpers de setup reutilizables en todo el archivo.
 * Se definen como funciones globales para evitar repetición en beforeEach anidados.
 */
function makeLoginData(string $email = 'user@example.com', string $password = 'password', ?string $recaptcha = null, string $device = 'Test Device'): LoginData
{
    return new LoginData(
        email: $email,
        password: $password,
        device_name: $device,
        remember: false,
        recaptcha_token: $recaptcha,
    );
}

function makeLoginAction(?LockoutStateStore $store = null): LoginAction
{
    $store ??= new LockoutStateStore;

    return new LoginAction($store, new LockoutStateAction($store), new CheckAccountBlockStatusHelper);
}

/**
 * Ejecutar N intentos fallidos de login absorbiendo todas las excepciones.
 * Útil para llevar el Rate Limiter a un estado conocido sin romper el test.
 */
function exhaustLoginAttempts(LoginAction $action, int $times, string $email = 'user@example.com'): void
{
    foreach (range(1, $times) as $_) {
        try {
            $action->login(makeLoginData(email: $email, password: 'wrong', recaptcha: 'token'), '127.0.0.1');
        } catch (ValidationException|LoginThrottledException) {
        }
    }
}

/** @var LoginAction $action */
$action = makeLoginAction();

beforeEach(function () use (&$action) {
    Cache::flush();
    RateLimiter::clear('user@example.com|127.0.0.1');
    $action = makeLoginAction();
});

// ─────────────────────────────────────────────────────────────
// Login exitoso
// ─────────────────────────────────────────────────────────────

describe('login exitoso', function () use (&$action) {
    it('retorna un token Sanctum con credenciales correctas', function () use (&$action) {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $result = $action->login(makeLoginData(), '127.0.0.1');

        // Con API tokens el login es stateless — no queda sesión activa.
        // El resultado correcto es un token de acceso válido.
        expect($result->token)->not->toBeEmpty()->toBeString();
    });

    it('limpia el cache de lockout al autenticarse correctamente', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');

        $action = makeLoginAction($store);
        $action->login(makeLoginData(recaptcha: 'token'), '127.0.0.1');

        expect($store->isCaptchaRequired('user@example.com'))->toBeFalse();
    });

    it('limpia el Rate Limiter al autenticarse correctamente', function () use (&$action) {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        // Generar algunos intentos fallidos previos para que el Rate Limiter tenga estado
        try {
            $action->login(makeLoginData(password: 'wrong'), '127.0.0.1');
        } catch (ValidationException) {
        }

        $action->login(makeLoginData(), '127.0.0.1');

        $key = Str::transliterate(Str::lower('user@example.com').'|127.0.0.1');
        expect(RateLimiter::attempts($key))->toBe(0);
    });
});

// ─────────────────────────────────────────────────────────────
// Credenciales incorrectas
// ─────────────────────────────────────────────────────────────

describe('credenciales incorrectas', function () use (&$action) {
    it('lanza ValidationException con el código LoginFailed', function () use (&$action) {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        expect(fn () => $action->login(makeLoginData(password: 'wrongpassword'), '127.0.0.1'))
            ->toThrow(ValidationException::class);
    });

    it('el error de credenciales incorrectas va en el campo email', function () use (&$action) {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        try {
            $action->login(makeLoginData(password: 'wrong'), '127.0.0.1');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('email')
                ->and($e->errors()['email'][0])->toBe(AuthErrorCode::LoginFailed->value);
        }
    });
});

// ─────────────────────────────────────────────────────────────
// Throttle y Lockout
// ─────────────────────────────────────────────────────────────

describe('throttle - lockout', function () use (&$action) {
    it('lanza LoginThrottledException al agotar los intentos disponibles', function () use (&$action) {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $maxAttempts = (int) config('auth.login.ip.max_attempts', 3);

        // Agotar todos los intentos: el último provoca el lockout
        exhaustLoginAttempts($action, $maxAttempts);

        // El siguiente intento debe disparar LoginThrottledException
        expect(fn () => $action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1'))
            ->toThrow(LoginThrottledException::class);
    });

    it('la LoginThrottledException incluye retry_after y captcha_required', function () use (&$action) {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $maxAttempts = (int) config('auth.login.ip.max_attempts', 3);
        exhaustLoginAttempts($action, $maxAttempts);

        try {
            $action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1');
        } catch (LoginThrottledException $e) {
            $data = $e->render()->getData(assoc: true);

            expect($data['data']['lockout']['retry_after'])->toBeInt()->toBeGreaterThan(0)
                ->and($data['data']['lockout']['captcha_required'])->toBeTrue()
                ->and($data['data']['lockout']['permanently_blocked'])->toBeFalse();
        }
    });

    it('dispara el evento Lockout de Laravel al alcanzar el límite', function () use (&$action) {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $maxAttempts = (int) config('auth.login.ip.max_attempts', 3);
        exhaustLoginAttempts($action, $maxAttempts + 1);

        Event::assertDispatched(Lockout::class);
    });
});

// ─────────────────────────────────────────────────────────────
// Bloqueos de cuenta (DB)
// ─────────────────────────────────────────────────────────────

describe('usuario bloqueado temporalmente', function () use (&$action) {
    it('no bloquea el login si security_status es TEMPORARILY_BLOCKED pero blocked_until ya expiró', function () use (&$action) {
        // isTemporarilyBlocked() requiere security_status + blocked_until futuro.
        // blocked_until proviene de la vista user_current_security_state, no de users,
        // así que al leer el User directamente blocked_until es null → no se bloquea.
        // Este test documenta ese comportamiento y sirve de guarda ante refactors del modelo.
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'security_status' => SecurityStatusEnum::TEMPORARILY_BLOCKED,
        ]);

        // Con blocked_until null, isTemporarilyBlocked() devuelve false → login procede
        $result = $action->login(makeLoginData(), '127.0.0.1');

        expect($result->token)->not->toBeEmpty()->toBeString();
    });
});

describe('usuario bloqueado permanentemente', function () use (&$action) {
    it('lanza ValidationException con ThirdLockoutFired para cuentas bloqueadas permanentemente', function () use (&$action) {
        User::factory()->create([
            'email' => 'user@example.com',
            'security_status' => SecurityStatusEnum::PERMANENTLY_BLOCKED,
        ]);

        try {
            $action->login(makeLoginData(), '127.0.0.1');
            $this->fail('Se esperaba una ValidationException');
        } catch (ValidationException $e) {
            expect(json_encode($e->errors()))->toContain(AuthErrorCode::ThirdLockoutFired->value);
        }
    });

    it('no consume intentos del Rate Limiter si la cuenta está bloqueada permanentemente', function () use (&$action) {
        User::factory()->create([
            'email' => 'user@example.com',
            'security_status' => SecurityStatusEnum::PERMANENTLY_BLOCKED,
        ]);

        try {
            $action->login(makeLoginData(), '127.0.0.1');
        } catch (ValidationException) {
        }

        $key = Illuminate\Support\Str::transliterate(Illuminate\Support\Str::lower('user@example.com').'|127.0.0.1');
        expect(RateLimiter::attempts($key))->toBe(0);
    });
});

// ─────────────────────────────────────────────────────────────
// reCAPTCHA requerido
// ─────────────────────────────────────────────────────────────

describe('captcha requerido', function () use (&$action) {
    it('lanza ValidationException con CaptchaVerificationRequired si falta el token', function () {
        User::factory()->create(['email' => 'user@example.com']);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');
        $localAction = makeLoginAction($store);

        try {
            $localAction->login(makeLoginData(recaptcha: null), '127.0.0.1');
            $this->fail('Se esperaba una ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('recaptcha_token')
                ->and($e->errors()['recaptcha_token'][0])->toBe(AuthErrorCode::CaptchaVerificationRequired->value);
        }
    });

    it('no lanza error de captcha si el token está provisto', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');
        $localAction = makeLoginAction($store);

        $threw = null;
        try {
            $localAction->login(makeLoginData(recaptcha: 'some-token'), '127.0.0.1');
        } catch (ValidationException $e) {
            $threw = $e;
        }

        expect(json_encode($threw?->errors() ?? []))
            ->not->toContain(AuthErrorCode::CaptchaVerificationRequired->value);
    });

    it('no exige captcha si no está activado en cache', function () use (&$action) {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        // Sin activar captcha en store, login sin token debe proceder sin error de captcha
        $threw = null;
        try {
            $action->login(makeLoginData(recaptcha: null), '127.0.0.1');
        } catch (ValidationException $e) {
            $threw = $e;
        }

        expect(json_encode($threw?->errors() ?? []))
            ->not->toContain(AuthErrorCode::CaptchaVerificationRequired->value);
    });
});
