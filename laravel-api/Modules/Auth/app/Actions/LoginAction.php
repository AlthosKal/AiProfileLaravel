<?php

namespace Modules\Auth\Actions;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Exceptions\LoginThrottledException;
use Modules\Auth\Http\Data\LoginData;
use Modules\Auth\Interfaces\LockoutStateStoreInterface;
use Modules\Auth\Models\User;
use Throwable;

class LoginAction
{
    public function __construct(
        private readonly LockoutStateStoreInterface $lockoutStore,
        private readonly LockoutStateAction $lockoutStateAction,
    ) {}

    /** @throws Throwable */
    public function login(LoginData $data, string $ip): void
    {
        $this->checkPermanentBlock($data->email);
        $this->checkCaptchaRequirement($data);

        $key = $this->throttleKey($data->email, $ip);
        $lockoutCount = $this->lockoutStore->getLockoutCount($data->email);
        $maxAttempts = $lockoutCount === 0 ? 5 : 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->fireLockout($data->email, $ip);
        }

        if (! Auth::attempt(['email' => $data->email, 'password' => $data->password], $data->remember ?? false)) {
            $decaySeconds = $lockoutCount === 0 ? 60 : 3600;
            RateLimiter::hit($key, $decaySeconds);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $this->fireLockout($data->email, $ip);
            }

            throw ValidationException::withMessages([
                'email' => AuthErrorCode::LoginFailed->value,
            ]);
        }

        RateLimiter::clear($key);
        $this->lockoutStore->clearLockoutData($data->email);
    }

    /** @throws Throwable */
    private function fireLockout(string $email, string $ip): never
    {
        event(new Lockout(request()));

        $lockoutState = $this->lockoutStateAction->handleLockout($email, $ip);

        throw new LoginThrottledException($lockoutState);
    }

    private function checkPermanentBlock(string $email): void
    {
        $user = User::where('email', $email)->first();

        if ($user?->isPermanentlyBlocked()) {
            throw ValidationException::withMessages([
                'email' => AuthErrorCode::ThirdLockoutFired->value,
            ]);
        }
    }

    private function checkCaptchaRequirement(LoginData $data): void
    {
        if (! $this->lockoutStore->isCaptchaRequired($data->email)) {
            return;
        }

        if (empty($data->recaptcha_token)) {
            throw ValidationException::withMessages([
                'recaptcha_token' => AuthErrorCode::CaptchaVerificationRequired->value,
            ]);
        }
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
