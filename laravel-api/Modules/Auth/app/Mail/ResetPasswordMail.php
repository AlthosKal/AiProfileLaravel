<?php

namespace Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\Enums\PasswordResetReason;
use Modules\Auth\Models\User;

/**
 * Mailable para el reset de contraseña.
 *
 * Cubre dos casos de uso distintos controlados por PasswordResetReason:
 *   - FORGOT_PASSWORD: el usuario solicitó recuperar su contraseña
 *   - EXPIRED_PASSWORD: la contraseña venció por política de seguridad
 *
 * La razón determina el contenido contextual del correo (vista),
 * mientras que el asunto permanece igual para ambos casos.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
        public readonly PasswordResetReason $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Restablecimiento de Contraseña',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'auth::emails.reset-password',
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
