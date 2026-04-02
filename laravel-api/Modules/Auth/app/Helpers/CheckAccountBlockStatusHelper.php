<?php

namespace Modules\Auth\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Models\User;

class CheckAccountBlockStatusHelper
{
    /**
     * Verificar si la cuenta del email provisto tiene algún tipo de bloqueo activo.
     *
     * Se hace una sola query para obtener el usuario y luego se evalúan ambos
     * estados en orden de severidad: temporal primero, permanente después.
     * Si el usuario no existe aún, no se lanza excepción (evita enumeración de cuentas).
     *
     * @throws ValidationException
     */
    public function check(string $email): void
    {
        $user = User::where('email', $email)->first();

        if ($user?->isTemporarilyBlocked()) {
            Log::warning("Login rechazado para $email : cuenta bloqueada temporalmente.");

            throw ValidationException::withMessages([
                'email' => AuthErrorCode::SecondLockoutFired->value,
            ]);
        }

        if ($user?->isPermanentlyBlocked()) {
            Log::warning("Login rechazado para $email : cuenta bloqueada permanentemente.");

            throw ValidationException::withMessages([
                'email' => AuthErrorCode::ThirdLockoutFired->value,
            ]);
        }
    }
}
