<?php

namespace Modules\Auth\Security;

use Illuminate\Validation\Rules\Password;

class PasswordSecurity
{
    public static function default(): Password
    {
        $rules = Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();

        Password::defaults(fn () => $rules);

        return $rules;
    }
}
