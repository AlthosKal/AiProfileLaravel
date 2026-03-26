<?php

namespace Modules\Auth\Actions;

use Auth;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Http\Data\LoginData;
use RateLimiter;
use Str;

class LoginAction
{
    public function login(LoginData $data, string $ip): void
    {
        $key = $this->throttleKey($data->email, $ip);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout(request()));

            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::attempt(['email', $data->email, 'password' => $data->password], $data->remember ?? false)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
               'email' => __('auth.failed'),
            ]);
        }
        RateLimiter::clear($key);
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email) . '|' . $ip);
    }
}
