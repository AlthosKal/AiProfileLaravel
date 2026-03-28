<?php

namespace Modules\Auth\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Exceptions\UserNotFoundForLockoutException;
use Modules\Auth\Http\Data\LockoutStateData;
use Modules\Auth\Interfaces\LockoutStateStoreInterface;
use Modules\Auth\Models\User;
use Modules\Auth\Models\UserSecurityEvent;
use Throwable;

class LockoutStateAction
{
    public function __construct(
        private readonly LockoutStateStoreInterface $store,
    ) {}

    /** @throws Throwable */
    public function handleLockout(string $email, string $ip): LockoutStateData
    {
        $count = $this->store->incrementLockoutCount($email);

        Log::info("Conteo para el bloqueo incrementado para $email. El conteo fue aumentado a $count");

        return match ($count) {
            1 => $this->handleFirstLockout($email, $count),
            2 => $this->handleSecondLockout($email, $count),
            default => $this->handleThirdLockout($email, $count, $ip),
        };
    }

    private function handleFirstLockout(string $email, int $count): LockoutStateData
    {
        $duration = 60;

        $this->store->enableCaptcha($email);
        $this->saveExpiryTimestamp($email, $duration);

        Log::warning("Primer Lockout disparado para $email. Bloqueo por 1 minuto. El conteo fue aumentado a $count.");

        return new LockoutStateData(
            permanent: false,
            count: $count,
            captcha_enabled: true,
            duration: $duration,
            retry_after: $duration,
            errorCode: AuthErrorCode::FirstLockoutFired->value,
        );
    }

    private function handleSecondLockout(string $email, int $count): LockoutStateData
    {
        $duration = 3600;

        $this->saveExpiryTimestamp($email, $duration);

        Log::warning("Segundo Lockout disparado para $email. Bloqueo por 1 hora. El conteo fue aumentado a $count.");

        return new LockoutStateData(
            permanent: false,
            count: $count,
            captcha_enabled: true,
            duration: $duration,
            retry_after: $duration,
            errorCode: AuthErrorCode::SecondLockoutFired->value,
        );
    }

    private function saveExpiryTimestamp(string $email, int $seconds): void
    {
        Cache::put(
            key: 'lockout:expiry:'.md5($email),
            value: now()->addSeconds($seconds)->timestamp,
            ttl: $seconds,
        );
    }

    /** @throws Throwable */
    private function handleThirdLockout(string $email, int $count, string $ip): LockoutStateData
    {
        $user = User::where('email', $email)->first();

        throw_if(
            ! $user,
            new UserNotFoundForLockoutException(
                message: "Usuario $email no encontrado",
                details: ['email' => $email, 'count' => $count],
            )
        );

        $user->update(['security_status' => SecurityStatusEnum::PERMANENTLY_BLOCKED->value]);

        $securityEvent = UserSecurityEvent::logPermanentBlock(
            user: $user,
            ipAddress: $ip,
            reason: "Bloqueo permanente automático al llegar a $count lockouts disparados",
            lockoutCount: $count,
        );

        $this->store->clearLockoutData($email);

        Log::alert("Tercer Lockout disparado para $email. Bloqueo PERMANENTE. El conteo fue aumentado a $count.");

        return new LockoutStateData(
            permanent: true,
            count: $count,
            user_security_event: $securityEvent,
            errorCode: AuthErrorCode::ThirdLockoutFired->value,
        );
    }
}
