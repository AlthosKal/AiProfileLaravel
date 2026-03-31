<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Actions\Auth\LockoutStateAction;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Exceptions\UserNotFoundForLockoutException;
use Modules\Auth\Http\Data\LockoutStateData;
use Modules\Auth\Models\User;
use Modules\Auth\Models\UserSecurityEvent;
use Modules\Auth\Stores\LockoutStateStore;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function lockoutStore(): LockoutStateStore
{
    return new LockoutStateStore;
}

function lockoutAction(): LockoutStateAction
{
    return new LockoutStateAction(lockoutStore());
}

describe('primer lockout', function () {
    it('retorna LockoutStateData con duración de 1 minuto y captcha activado', function () {
        $result = lockoutAction()->handleLockout('user@example.com', '127.0.0.1');

        expect($result)->toBeInstanceOf(LockoutStateData::class)
            ->and($result->permanent)->toBeFalse()
            ->and($result->count)->toBe(1)
            ->and($result->captcha_enabled)->toBeTrue()
            ->and($result->duration)->toBe(60)
            ->and($result->retry_after)->toBe(60)
            ->and($result->errorCode)->toBe(AuthErrorCode::FirstLockoutFired);
    });

    it('activa el captcha en cache', function () {
        lockoutAction()->handleLockout('user@example.com', '127.0.0.1');

        expect(lockoutStore()->isCaptchaRequired('user@example.com'))->toBeTrue();
    });

    it('guarda el timestamp de expiración en cache', function () {
        lockoutAction()->handleLockout('user@example.com', '127.0.0.1');

        $expiry = Cache::get('lockout:expiry:'.md5('user@example.com'));

        expect($expiry)->toBeInt()->toBeGreaterThan(now()->timestamp);
    });
});

describe('segundo lockout', function () {
    it('retorna LockoutStateData con duración de 1 hora', function () {
        $store = lockoutStore();
        $action = new LockoutStateAction($store);

        $action->handleLockout('user@example.com', '127.0.0.1');
        $result = $action->handleLockout('user@example.com', '127.0.0.1');

        expect($result->permanent)->toBeFalse()
            ->and($result->count)->toBe(2)
            ->and($result->captcha_enabled)->toBeTrue()
            ->and($result->duration)->toBe(3600)
            ->and($result->retry_after)->toBe(3600)
            ->and($result->errorCode)->toBe(AuthErrorCode::SecondLockoutFired);
    });

    it('actualiza el timestamp de expiración en cache a 1 hora', function () {
        $store = lockoutStore();
        $action = new LockoutStateAction($store);

        $action->handleLockout('user@example.com', '127.0.0.1');
        $action->handleLockout('user@example.com', '127.0.0.1');

        $expiry = Cache::get('lockout:expiry:'.md5('user@example.com'));

        expect($expiry)->toBeGreaterThan(now()->addMinutes(59)->timestamp);
    });
});

describe('tercer lockout (bloqueo permanente)', function () {
    it('bloquea permanentemente al usuario y retorna permanent=true', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $store = lockoutStore();
        $action = new LockoutStateAction($store);

        $action->handleLockout('user@example.com', '127.0.0.1');
        $action->handleLockout('user@example.com', '127.0.0.1');
        $result = $action->handleLockout('user@example.com', '127.0.0.1');

        expect($result->permanent)->toBeTrue()
            ->and($result->count)->toBe(3)
            ->and($result->retry_after)->toBeNull()
            ->and($result->errorCode)->toBe(AuthErrorCode::ThirdLockoutFired);

        expect($user->fresh()->security_status)->toBe(SecurityStatusEnum::PERMANENTLY_BLOCKED);
    });

    it('crea un registro permanent_block en user_security_events', function () {
        User::factory()->create(['email' => 'user@example.com']);
        $store = lockoutStore();
        $action = new LockoutStateAction($store);

        $action->handleLockout('user@example.com', '127.0.0.1');
        $action->handleLockout('user@example.com', '127.0.0.1');
        $action->handleLockout('user@example.com', '127.0.0.1');

        expect(UserSecurityEvent::where('user_email', 'user@example.com')
            ->where('event_type', SecurityStatusEnum::PERMANENTLY_BLOCKED->value)
            ->exists()
        )->toBeTrue();
    });

    it('limpia todo el estado de lockout en cache tras el bloqueo permanente', function () {
        User::factory()->create(['email' => 'user@example.com']);
        $store = lockoutStore();
        $action = new LockoutStateAction($store);

        $action->handleLockout('user@example.com', '127.0.0.1');
        $action->handleLockout('user@example.com', '127.0.0.1');
        $action->handleLockout('user@example.com', '127.0.0.1');

        expect($store->getLockoutCount('user@example.com'))->toBe(0)
            ->and($store->isCaptchaRequired('user@example.com'))->toBeFalse()
            ->and(Cache::get('lockout:expiry:'.md5('user@example.com')))->toBeNull();
    });

    it('lanza UserNotFoundForLockoutException si el usuario no existe', function () {
        $store = lockoutStore();
        $action = new LockoutStateAction($store);

        $action->handleLockout('noexiste@example.com', '127.0.0.1');
        $action->handleLockout('noexiste@example.com', '127.0.0.1');

        expect(fn () => $action->handleLockout('noexiste@example.com', '127.0.0.1'))
            ->toThrow(UserNotFoundForLockoutException::class);
    });
});
