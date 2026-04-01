<?php

namespace Modules\Auth\Actions\Auth;

use Illuminate\Auth\Events\Registered;
use Modules\Auth\Http\Data\RegisterUserData;
use Modules\Auth\Models\PasswordHistory;
use Modules\Auth\Models\User;

/**
 * Acción encargada de registrar un nuevo usuario en el sistema.
 *
 * Flujo de registro en orden:
 *   1. Crear el usuario en la base de datos
 *   2. Registrar la contraseña inicial en el historial de contraseñas
 *   3. Disparar el evento Registered para iniciar el flujo de verificación de email
 *   4. Registrar la actividad en el log de auditoría
 */
class RegisterUserAction
{
    /**
     * Ejecutar el proceso completo de registro de un nuevo usuario.
     *
     * @param  RegisterUserData  $data  Datos validados del request de registro
     */
    public function register(RegisterUserData $data): void
    {
        // El cast `hashed` en el modelo aplica Hash::make() automáticamente
        // al asignar la contraseña, por lo que se pasa en texto plano.
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
            'identification_number' => $data->identification_number,
            'identification_type' => $data->identification_type,
            'password_changed_at' => now(),
        ]);

        // El cast `hashed` en PasswordHistory aplica Hash::make() automáticamente,
        // por lo que también se pasa en texto plano.
        PasswordHistory::create([
            'user_email' => $data->email,
            'password' => $data->password,
        ]);

        // Dispara el evento nativo de Laravel que activa el envío del
        // correo de verificación de email (MustVerifyEmail).
        event(new Registered($user));

        activity('usuarios')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->log("Usuario $user->name, con correo $user->email, con número de identificación $user->identification_number y con tipo de identificación $user->identification_type registrado correctamente");
    }
}
