<?php

namespace Modules\Auth\Models\Concerns;

use Modules\Auth\Enums\SecurityEventTypeEnum;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Models\User;

/**
 * Factory methods para registrar eventos de seguridad en `user_security_events`.
 *
 * Cada método encapsula la estructura del registro correspondiente,
 * asegurando consistencia en los campos `event_type`, `metadata` y `expires_at`
 * sin exponer esos detalles al código que invoca el log.
 */
trait LogsSecurityEvents
{
    /**
     * Registrar un evento de bloqueo temporal.
     *
     * Calcula `expires_at` sumando `$minutes` a partir del momento actual
     * y registra en metadata quién lo disparó (admin o sistema).
     */
    public static function logTemporaryBlock(
        User $user,
        string $ipAddress,
        string $reason,
        int $minutes,
        int $lockoutCount,
        ?User $triggeredBy = null,
    ): static {
        return static::query()->create([
            'user_email' => $user->email,
            'event_type' => SecurityStatusEnum::TEMPORARILY_BLOCKED->value,
            'ip_address' => $ipAddress,
            'reason' => $reason,
            'event_at' => now(),
            'expires_at' => now()->addMinutes($minutes),
            'lockout_count_at_time' => $lockoutCount,
            'metadata' => [
                'duration_minutes' => $minutes,
                'user_agent' => request()->userAgent(),
                'triggered_by_type' => $triggeredBy ? 'admin' : 'system',
            ],
        ]);
    }

    /**
     * Registrar un evento de bloqueo permanente.
     *
     * `expires_at` es null porque el bloqueo no tiene vencimiento.
     */
    public static function logPermanentBlock(
        User $user,
        string $ipAddress,
        string $reason,
        ?User $admin = null,
        int $lockoutCount = 0,
    ): static {
        return static::query()->create([
            'user_email' => $user->email,
            'event_type' => SecurityStatusEnum::PERMANENTLY_BLOCKED->value,
            'ip_address' => $ipAddress,
            'reason' => $reason,
            'event_at' => now(),
            'expires_at' => null,
            'lockout_count_at_time' => $lockoutCount,
            'metadata' => [
                'triggered_by_type' => $admin ? 'admin' : 'system',
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }

    /**
     * Registrar un auto-desbloqueo cuando expira el bloqueo temporal.
     */
    public static function logAutoUnblock(
        User $user,
        string $reason = 'Bloqueo temporal expirado',
    ): static {
        return static::query()->create([
            'user_email' => $user->email,
            'event_type' => SecurityEventTypeEnum::AUTO_UNBLOCK->value,
            'ip_address' => request()->ip(),
            'reason' => $reason,
            'event_at' => now(),
            'expires_at' => null,
            'lockout_count_at_time' => 0,
            'metadata' => [
                'triggered_by_type' => 'system',
            ],
        ]);
    }
}
