<?php

namespace Modules\Auth\Actions\Password;

use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\PasswordHistory;
use Modules\Auth\Models\User;

/**
 * Acción que centraliza la actualización de contraseña de un usuario.
 *
 * Encapsula las tres responsabilidades que deben ocurrir en conjunto
 * cada vez que se cambia una contraseña:
 *   1. Guardar la contraseña actual en el historial antes de reemplazarla.
 *   2. Actualizar el hash y registrar la fecha del cambio (`password_changed_at`).
 *   3. Limpiar el historial antiguo manteniendo solo las últimas N entradas.
 *
 * Usada por ResetPasswordAction para no duplicar esta lógica.
 */
readonly class UpdateUserPasswordAction
{
    /**
     * Actualizar la contraseña del usuario y gestionar su historial.
     *
     * @param  User  $user  Usuario al que se le actualizará la contraseña
     * @param  string  $newPassword  Nueva contraseña en texto plano
     * @return User Usuario con los datos frescos de la base de datos
     */
    public function update(User $user, string $newPassword): User
    {
        // Guardar la contraseña actual en el historial antes de reemplazarla.
        // Solo si ya existe una contraseña (el hash ya está guardado en DB).
        if ($user->isPasswordExists()) {
            PasswordHistory::create([
                'user_email' => $user->email,
                'password' => $user->password,
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        $user->cleanOldPasswordHistories();

        return $user->fresh();
    }
}
