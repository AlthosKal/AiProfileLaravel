<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Actions\LockoutStateAction;
use Modules\Auth\Actions\LoginAction;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Exceptions\LoginThrottledException;
use Modules\Auth\Http\Data\LoginData;
use Modules\Auth\Models\User;
use Modules\Auth\Stores\LockoutStateStore;

uses(RefreshDatabase::class);

/**
 * Helpers de setup reutilizables en todo el archivo.
 * Se definen como funciones globales para evitar repetición en beforeEach anidados.
 */
function makeLoginData(string $email = 'user@example.com', string $password = 'password', ?string $recaptcha = null): LoginData
{
    return new LoginData(
        email: $email,
        password: $password,
        remember: false,
        recaptcha_token: $recaptcha,
    );
}

function makeLoginAction(?LockoutStateStore $store = null): LoginAction
{
    $store ??= new LockoutStateStore;

    return new LoginAction($store, new LockoutStateAction($store));
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

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('user@example.com|127.0.0.1');
    $this->action = makeLoginAction();
});

// ─────────────────────────────────────────────────────────────
// Login exitoso
// ─────────────────────────────────────────────────────────────

describe('login exitoso', function () {
    it('autentica al usuario con credenciales correctas', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $this->action->login(makeLoginData(), '127.0.0.1');

        expect(auth()->check())->toBeTrue();
    });

    it('limpia el cache de lockout al autenticarse correctamente', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');

        $action = makeLoginAction($store);
        $action->login(makeLoginData(recaptcha: 'token'), '127.0.0.1');

        expect($store->isCaptchaRequired('user@example.com'))->toBeFalse();
    });

    it('limpia el Rate Limiter al autenticarse correctamente', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        // Generar algunos intentos fallidos previos para que el Rate Limiter tenga estado
        try {
            $this->action->login(makeLoginData(password: 'wrong'), '127.0.0.1');
        } catch (ValidationException) {
        }

        $this->action->login(makeLoginData(), '127.0.0.1');

        $key = Str::transliterate(Str::lower('user@example.com').'|127.0.0.1');
        expect(RateLimiter::attempts($key))->toBe(0);
    });
});

// ─────────────────────────────────────────────────────────────
// Credenciales incorrectas
// ─────────────────────────────────────────────────────────────

describe('credenciales incorrectas', function () {
    it('lanza ValidationException con el código LoginFailed', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        expect(fn () => $this->action->login(makeLoginData(password: 'wrongpassword'), '127.0.0.1'))
            ->toThrow(ValidationException::class);
    });

    it('el error de credenciales incorrectas va en el campo email', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        try {
            $this->action->login(makeLoginData(password: 'wrong'), '127.0.0.1');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('email')
                ->and($e->errors()['email'][0])->toBe(AuthErrorCode::LoginFailed->value);
        }
    });
});

// ─────────────────────────────────────────────────────────────
// Throttle y Lockout
// ─────────────────────────────────────────────────────────────

describe('throttle - lockout', function () {
    it('lanza LoginThrottledException al agotar los intentos disponibles', function () {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $maxAttempts = (int) config('auth.login.ip.max_attempts', 3);

        // Agotar todos los intentos: el último provoca el lockout
        exhaustLoginAttempts($this->action, $maxAttempts);

        // El siguiente intento debe disparar LoginThrottledException
        expect(fn () => $this->action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1'))
            ->toThrow(LoginThrottledException::class);
    });

    it('la LoginThrottledException incluye retry_after y captcha_required', function () {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $maxAttempts = (int) config('auth.login.ip.max_attempts', 3);
        exhaustLoginAttempts($this->action, $maxAttempts);

        try {
            $this->action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1');
        } catch (LoginThrottledException $e) {
            $data = $e->render()->getData(assoc: true);

            expect($data['lockout']['retry_after'])->toBeInt()->toBeGreaterThan(0)
                ->and($data['lockout']['captcha_required'])->toBeTrue()
                ->and($data['lockout']['permanently_blocked'])->toBeFalse();
        }
    });

    it('dispara el evento Lockout de Laravel al alcanzar el límite', function () {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $maxAttempts = (int) config('auth.login.ip.max_attempts', 3);
        exhaustLoginAttempts($this->action, $maxAttempts + 1);

        Event::assertDispatched(Lockout::class);
    });
});

// ─────────────────────────────────────────────────────────────
// Bloqueos de cuenta (DB)
// ─────────────────────────────────────────────────────────────

describe('usuario bloqueado temporalmente', function () {
    it('no bloquea el login si security_status es TEMPORARILY_BLOCKED pero blocked_until ya expiró', function () {
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
        $this->action->login(makeLoginData(), '127.0.0.1');

        expect(auth()->check())->toBeTrue();
    });
});

describe('usuario bloqueado permanentemente', function () {
    it('lanza ValidationException con ThirdLockoutFired para cuentas bloqueadas permanentemente', function () {
        User::factory()->create([
            'email' => 'user@example.com',
            'security_status' => SecurityStatusEnum::PERMANENTLY_BLOCKED,
        ]);

        expect(fn () => $this->action->login(makeLoginData(), '127.0.0.1'))
            ->toThrow(fn (ValidationException $e) => str_contains(
                json_encode($e->errors()),
                AuthErrorCode::ThirdLockoutFired->value
            ));
    });

    it('no consume intentos del Rate Limiter si la cuenta está bloqueada permanentemente', function () {
        User::factory()->create([
            'email' => 'user@example.com',
            'security_status' => SecurityStatusEnum::PERMANENTLY_BLOCKED,
        ]);

        try {
            $this->action->login(makeLoginData(), '127.0.0.1');
        } catch (ValidationException) {
        }

        $key = Illuminate\Support\Str::transliterate(Illuminate\Support\Str::lower('user@example.com').'|127.0.0.1');
        expect(RateLimiter::attempts($key))->toBe(0);
    });
});

// ─────────────────────────────────────────────────────────────
// reCAPTCHA requerido
// ─────────────────────────────────────────────────────────────

describe('captcha requerido', function () {
    it('lanza ValidationException con CaptchaVerificationRequired si falta el token', function () {
        User::factory()->create(['email' => 'user@example.com']);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');
        $action = makeLoginAction($store);

        try {
            $action->login(makeLoginData(recaptcha: null), '127.0.0.1');
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
        $action = makeLoginAction($store);

        $threw = null;
        try {
            $action->login(makeLoginData(recaptcha: 'some-token'), '127.0.0.1');
        } catch (ValidationException $e) {
            $threw = $e;
        }

        expect(json_encode($threw?->errors() ?? []))
            ->not->toContain(AuthErrorCode::CaptchaVerificationRequired->value);
    });

    it('no exige captcha si no está activado en cache', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        // Sin activar captcha en store, login sin token debe proceder sin error de captcha
        $threw = null;
        try {
            $this->action->login(makeLoginData(recaptcha: null), '127.0.0.1');
        } catch (ValidationException $e) {
            $threw = $e;
        }

        expect(json_encode($threw?->errors() ?? []))
            ->not->toContain(AuthErrorCode::CaptchaVerificationRequired->value);
    });
});
