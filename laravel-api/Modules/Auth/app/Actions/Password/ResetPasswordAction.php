<?php

namespace Modules\Auth\Actions\Password;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Http\Data\ResetPasswordData;
use Throwable;

/**
 * Acción para completar el proceso de reset de contraseña.
 *
 * Delega la validación del token y la búsqueda del usuario al password broker
 * de Laravel (`Password::reset`), y dentro del callback invoca
 * `UpdateUserPasswordAction` para centralizar la lógica de actualización
 * de contraseña, historial y `password_changed_at`.
 *
 * Si el broker reporta cualquier fallo (token inválido, expirado, usuario no
 * encontrado), se lanza una `ValidationException` con clave semántica en el
 * campo `email` para que el frontend pueda reaccionar sin mensajes en texto plano.
 */
readonly class ResetPasswordAction
{
    public function __construct(
        private UpdateUserPasswordAction $updateUserPasswordAction,
    ) {}

    /**
     * Ejecutar el reset de contraseña.
     *
     * @param  ResetPasswordData  $data  DTO con token, email y nueva contraseña
     * @return string Clave semántica de éxito para el frontend
     *
     * @throws ValidationException Si el token es inválido o el usuario no existe
     * @throws Throwable
     */
    public function update(ResetPasswordData $data): string
    {
        // El broker solo necesita token, email y password para validar y buscar al usuario.
        // No se pasa el array completo del DTO para evitar que campos extra (recaptcha_token)
        // interfieran con la búsqueda del UserProvider.
        $status = Password::reset(
            ['token' => $data->token, 'email' => $data->email, 'password' => $data->password],
            function ($user) use ($data): void {
                // Actualizar contraseña, password_changed_at e historial
                $this->updateUserPasswordAction->update($user, $data->password);

                // Rotar el remember_token para invalidar sesiones "recordadas" previas
                $user->forceFill(['remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            }
        );

        throw_if(
            $status !== Password::PASSWORD_RESET,
            ValidationException::withMessages([
                'email' => AuthErrorCode::PasswordResetFailed->value,
            ])
        );

        return AuthSuccessCode::PasswordResetSuccess->value;
    }
}
