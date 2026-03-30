<?php

namespace Modules\Auth\Models\Concerns;

use Illuminate\Support\Carbon;
use Modules\Auth\Enums\SecurityStatusEnum;

/**
 * Verificaciones de estado de seguridad para el modelo User.
 *
 * Agrupa los métodos que consultan el `security_status` del usuario
 * y el estado de 2FA, manteniendo esa lógica fuera del modelo principal.
 *
 * @property SecurityStatusEnum $security_status
 * @property Carbon|null $blocked_until
 * @property bool $is_two_factor_enabled
 * @property Carbon|null $two_factor_confirmed_at
 */
trait HasSecurityStatus
{
    /**
     * Verificar si está bloqueado (temporal o permanente).
     */
    public function isBlocked(): bool
    {
        return $this->security_status->isBlocked();
    }

    /**
     * Verificar si está bloqueado temporalmente.
     *
     * Requiere que el campo `blocked_until` exista y sea futuro,
     * ya que ese atributo proviene de la vista `user_security_state`,
     * no de la tabla `users` directamente.
     */
    public function isTemporarilyBlocked(): bool
    {
        return $this->security_status->isTemporarilyBlocked()
            && $this->blocked_until
            && $this->blocked_until->isFuture();
    }

    /**
     * Verificar si está bloqueado permanentemente.
     */
    public function isPermanentlyBlocked(): bool
    {
        return $this->security_status->isPermanentlyBlocked();
    }

    /**
     * Verificar si el 2FA está habilitado y completamente confirmado.
     *
     * No es suficiente con que esté habilitado — el usuario debe haber
     * completado el proceso de confirmación para que el desafío sea exigible.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->is_two_factor_enabled === true;
    }
}
