<?php

namespace Modules\Auth\Enums;

/**
 * Razones válidas para disparar un reset de contraseña.
 *
 * Centraliza los casos de uso del sistema de cambio de contraseña
 * para mantener consistencia y type safety entre el flujo de
 * recuperación estándar y el de expiración por política de seguridad.
 */
enum PasswordResetReason: string
{
    /**
     * El usuario solicitó recuperar su contraseña olvidada.
     * Es el flujo estándar iniciado desde el endpoint /forgot-password.
     */
    case FORGOT_PASSWORD = 'forgot_password';

    /**
     * La contraseña del usuario venció por política de seguridad.
     * El middleware de expiración fuerza este flujo antes de permitir
     * el acceso a rutas protegidas.
     */
    case EXPIRED_PASSWORD = 'expired_password';

    /**
     * Verificar si la razón es contraseña vencida.
     *
     * Usado en la vista del correo para mostrar el bloque
     * de advertencia correspondiente a este caso.
     *
     * Tener en cuenta que aunque el IDE lo marque como sin usar
     * en realidad si se está usando en la vista reset-password
     */
    public function isExpiredPassword(): bool
    {
        return $this === self::EXPIRED_PASSWORD;
    }

    /**
     * Verificar si la razón es que olvidó la contraseña.
     *
     * Usado en la vista del correo para mostrar el bloque
     * de advertencia correspondiente a este caso.
     *
     * Tener en cuenta que aunque el IDE lo marque como sin usar
     * en realidad si se está usando en la vista reset-password
     */
    public function isForgotPassword(): bool
    {
        return $this === self::FORGOT_PASSWORD;
    }
}
