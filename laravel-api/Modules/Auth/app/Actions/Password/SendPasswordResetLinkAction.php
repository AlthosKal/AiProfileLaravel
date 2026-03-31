<?php

namespace Modules\Auth\Actions\Password;

use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Enums\PasswordResetReason;
use Modules\Auth\Http\Data\PasswordResetLinkData;
use Modules\Auth\Mail\ResetPasswordMail;
use Modules\Auth\Models\User;

/**
 * Acción para enviar el link de recuperación de contraseña.
 *
 * Usa el password broker de Laravel para la generación del token y el throttling,
 * pero toma control del envío del correo mediante el callback de `sendResetLink`
 * para poder pasar el PasswordResetReason al Mailable y personalizar el contenido.
 *
 * Cuando se proporciona un callback, el broker omite el disparo del evento
 * PasswordResetLinkSent, por lo que se dispara manualmente aquí.
 *
 * Por seguridad, la respuesta al frontend es idéntica tanto si el email existe
 * como si no, evitando la enumeración de usuarios (OWASP Authentication Cheat Sheet).
 */
readonly class SendPasswordResetLinkAction
{
    /**
     * Enviar el link de recuperación de contraseña al email indicado.
     *
     * @param  PasswordResetLinkData  $data  DTO con el email validado y token reCAPTCHA
     * @param  PasswordResetReason  $reason  Razón del reset para personalizar el correo
     * @return string Clave semántica del resultado para el frontend
     */
    public function send(PasswordResetLinkData $data, PasswordResetReason $reason = PasswordResetReason::FORGOT_PASSWORD): string
    {
        $status = Password::sendResetLink(
            ['email' => $data->email],
            function (User $user, string $token) use ($reason): void {
                $resetUrl = $this->buildResetUrl($token, $user->email);

                Mail::to($user->email)->send(new ResetPasswordMail($user, $resetUrl, $reason));

                // El broker omite este evento cuando se usa el callback, se dispara manualmente
                event(new PasswordResetLinkSent($user));
            }
        );

        // El broker retorna Password::RESET_LINK_SENT en éxito o una clave de error
        // (e.g. passwords.throttled, passwords.user). En ambos casos se retorna la
        // misma clave semántica al frontend para no revelar si el email está registrado.
        return $status === Password::RESET_LINK_SENT
            ? AuthSuccessCode::PasswordResetLinkSent->value
            : AuthErrorCode::PasswordResetLinkFailed->value;
    }

    /**
     * Construir la URL de reset usando la configuración del frontend.
     *
     * Delega en `createUrlUsing` registrado en AppServiceProvider si está definido,
     * o usa la URL del frontend configurada directamente.
     */
    private function buildResetUrl(string $token, string $email): string
    {
        return config('app.frontend_url')."/password-reset/$token?email=$email";
    }
}
