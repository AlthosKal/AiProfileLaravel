<?php

namespace Modules\Auth\Actions\TwoFactor;

// Verifica si un usuario cuenta con el two factor del aplicativo habilitado
class TwoFactorAction
{
    public function handle(bool $is_two_factor_confirmed): void
    {
        if ($is_two_factor_confirmed) {
            request()->session()->put('two_factor_confirmed', true);
        }
    }
}
