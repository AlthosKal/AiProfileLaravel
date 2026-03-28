<?php

namespace Modules\Auth\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Enums\SecurityEventTypeEnum;
use Modules\Auth\Enums\SecurityStatusEnum;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $user_email
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 *
 * @method static Builder<static>|UserSecurityEvent newModelQuery()
 * @method static Builder<static>|UserSecurityEvent newQuery()
 * @method static Builder<static>|UserSecurityEvent query()
 *
 * @mixin Eloquent
 */
#[Fillable([
    'user_email',
    'event_type',
    'ip_address',
    'reason',
    'event_at',
    'expires_at',
    'lockout_count_at_time',
    'metadata',
])]
class UserSecurityEvent extends Model
{
    use HasUuids, LogsActivity;

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'expires_at' => 'datetime',
            'lockout_count_at_time' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Registrar un evento de bloqueo temporal
     */
    public static function logTemporaryBlock(
        User $user,
        string $ipAddress,
        string $reason,
        int $minutes,
        int $lockoutCount,
        ?User $triggeredBy = null
    ): self {
        return self::create([
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
     * Registrar un evento de bloqueo permanente
     */
    public static function logPermanentBlock(
        User $user,
        string $ipAddress,
        string $reason,
        ?User $admin = null,
        int $lockoutCount = 0
    ): self {
        return self::create([
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
     * Registrar un auto-desbloqueo (cuando expira el tiempo)
     */
    public static function logAutoUnblock(
        User $user,
        string $reason = 'Bloqueo temporal expirado'
    ): self {
        return self::create([
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

    /**
     * Obtener el bloqueo activo de un usuario (por email e IP)
     */
    public static function getActiveBlock(string $email, string $ip): Eloquent
    {
        return self::where('user_email', $email)
            ->where('ip_address', $ip)
            ->whereIn('event_type', [SecurityStatusEnum::TEMPORARILY_BLOCKED->value, SecurityStatusEnum::PERMANENTLY_BLOCKED->value])
            ->where(function ($query) {
                $query->whereNull('expires_at') // Bloqueo permanente
                    ->orWhere('expires_at', '>', now()); // Bloqueo temporal no expirado
            })
            ->latest('event_at')
            ->first();
    }

    /**
     * Desbloquear este evento (registrar desbloqueo)
     *
     * Soporta desbloqueos manuales y automáticos.
     */
    public function unblock(?User $admin = null, string $reason = 'Desbloqueo'): void
    {
        self::create([
            'user_email' => $this->user_email,
            'event_type' => SecurityEventTypeEnum::AUTO_UNBLOCK->value,
            'ip_address' => request()->ip() ?? 'unknown',
            'reason' => $reason,
            'event_at' => now(),
            'expires_at' => null,
            'lockout_count_at_time' => 0,
            'metadata' => [
                'triggered_by_type' => $admin ? 'admin' : 'system',
            ],
        ]);
    }

    /**
     * Verificar si está bloqueado PERMANENTEMENTE (NO temporal)
     *
     * Se verifica por cuenta (user_id), no por IP, para que el bloqueo
     * permanente aplique independientemente de desde qué IP intente el atacante.
     */
    public function isPermanentlyBlocked(string $email): bool
    {
        return self::where('user_email', $email)
            ->where('event_type', SecurityStatusEnum::PERMANENTLY_BLOCKED->value)
            ->whereNull('expires_at')
            ->exists();
    }

    /**
     * Configuración de Activity Log (Spatie)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'user_email',
                'event_type',
                'ip_address',
                'reason',
                'event_at',
                'expires_at',
                'triggered_by',
                'lockout_count_at_time',
                'metadata',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
