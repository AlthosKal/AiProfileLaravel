<?php

namespace Modules\Auth\Enums;

/**
 * Estado de seguridad del usuario
 *
 * Representa el estado de bloqueo por seguridad (automático por intentos fallidos)
 */
enum SecurityStatusEnum: string
{
    case UNBLOCKED = 'unblocked';
    case TEMPORARILY_BLOCKED = 'temporarily_blocked';
    case PERMANENTLY_BLOCKED = 'permanently_blocked';

    /**
     * Obtener label legible
     */
    public function label(): string
    {
        return match ($this) {
            self::UNBLOCKED => 'No bloqueado',
            self::TEMPORARILY_BLOCKED => 'Bloqueado Temporalmente',
            self::PERMANENTLY_BLOCKED => 'Bloqueado Permanentemente',
        };
    }

    /**
     * Verificar si está bloqueado (cualquier tipo)
     */
    public function isBlocked(): bool
    {
        return $this !== self::UNBLOCKED;
    }

    /**
     * Verificar si está en estado no bloqueado (sin bloqueos)
     */
    public function isUnblocked(): bool
    {
        return $this === self::UNBLOCKED;
    }

    /**
     * Verificar si es bloqueo temporal
     */
    public function isTemporarilyBlocked(): bool
    {
        return $this === self::TEMPORARILY_BLOCKED;
    }

    /**
     * Verificar si es bloqueo permanente
     */
    public function isPermanentlyBlocked(): bool
    {
        return $this === self::PERMANENTLY_BLOCKED;
    }
}
