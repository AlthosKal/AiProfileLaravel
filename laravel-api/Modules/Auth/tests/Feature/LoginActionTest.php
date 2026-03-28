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

beforeEach(function () {
    Cache::flush();
    $store = new LockoutStateStore;
    $this->action = new LoginAction($store, new LockoutStateAction($store));
});

function makeLoginData(string $email = 'user@example.com', string $password = 'password', ?string $recaptcha = null): LoginData
{
    return new LoginData(
        email: $email,
        password: $password,
        remember: false,
        recaptcha_token: $recaptcha,
    );
}

describe('login exitoso', function () {
    it('autentica al usuario con credenciales correctas', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $this->action->login(makeLoginData(), '127.0.0.1');

        expect(auth()->check())->toBeTrue();
    });

    it('limpia el estado de lockout al autenticarse correctamente', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');

        $action = new LoginAction($store, new LockoutStateAction($store));
        $action->login(makeLoginData(recaptcha: 'token'), '127.0.0.1');

        expect($store->isCaptchaRequired('user@example.com'))->toBeFalse();
    });
});

describe('credenciales incorrectas', function () {
    it('lanza ValidationException con el código LoginFailed', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        expect(fn () => $this->action->login(makeLoginData(password: 'wrongpassword'), '127.0.0.1'))
            ->toThrow(ValidationException::class);
    });
});

describe('throttle - lockout', function () {
    it('lanza LoginThrottledException al alcanzar el límite de intentos', function () {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        foreach (range(1, 5) as $_) {
            try {
                $this->action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1');
            } catch (ValidationException|LoginThrottledException) {
            }
        }

        expect(fn () => $this->action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1'))
            ->toThrow(LoginThrottledException::class);
    });

    it('la LoginThrottledException incluye retry_after y captcha_required', function () {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        foreach (range(1, 5) as $_) {
            try {
                $this->action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1');
            } catch (ValidationException|LoginThrottledException) {
            }
        }

        try {
            $this->action->login(makeLoginData(password: 'wrong', recaptcha: 'token'), '127.0.0.1');
        } catch (LoginThrottledException $e) {
            $data = $e->render()->getData(assoc: true);

            expect($data['lockout']['retry_after'])->toBeInt()->toBeGreaterThan(0)
                ->and($data['lockout']['captcha_required'])->toBeTrue()
                ->and($data['lockout']['permanently_blocked'])->toBeFalse();
        }
    });

    it('dispara el evento Lockout de Laravel al llegar al límite', function () {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        foreach (range(1, 6) as $_) {
            try {
                $this->action->login(makeLoginData(password: 'wrong'), '127.0.0.1');
            } catch (ValidationException|LoginThrottledException) {
            }
        }

        Event::assertDispatched(Lockout::class);
    });
});

describe('usuario bloqueado permanentemente', function () {
    it('lanza ValidationException con ThirdLockoutFired para usuarios bloqueados permanentemente', function () {
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
});

describe('captcha requerido', function () {
    it('lanza ValidationException con CaptchaVerificationRequired si falta el token', function () {
        User::factory()->create(['email' => 'user@example.com']);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');
        $action = new LoginAction($store, new LockoutStateAction($store));

        try {
            $action->login(makeLoginData(recaptcha: null), '127.0.0.1');
            $this->fail('Se esperaba una ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('recaptcha_token')
                ->and($e->errors()['recaptcha_token'][0])->toBe(AuthErrorCode::CaptchaVerificationRequired->value);
        }
    });

    it('no lanza excepción de captcha si el token está provisto', function () {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $store = new LockoutStateStore;
        $store->enableCaptcha('user@example.com');
        $action = new LoginAction($store, new LockoutStateAction($store));

        $threw = null;
        try {
            $action->login(makeLoginData(recaptcha: 'some-token'), '127.0.0.1');
        } catch (ValidationException $e) {
            $threw = $e;
        }

        expect(json_encode($threw?->errors() ?? []))
            ->not->toContain(AuthErrorCode::CaptchaVerificationRequired->value);
    });
});
