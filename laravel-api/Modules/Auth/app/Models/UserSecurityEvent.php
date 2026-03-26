<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSecurityEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSecurityEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSecurityEvent query()
 * @mixin \Eloquent
 */
#[Fillable([
    'user_id',
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
            'lockout_count_at_time' => 'datetime',
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
            'user_id' => $user->id,
            'event_type' => 'temporary_block',
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
            'user_id' => $user->id,
            'event_type' => 'permanent_block',
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
            'user_id' => $user->id,
            'event_type' => 'auto_unblock',
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
    public static function getActiveBlock(string $email, string $ip): ?self
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return null;
        }

        return self::where('user_id', $user->id)
            ->where('ip_address', $ip)
            ->whereIn('event_type', ['temporary_block', 'permanent_block'])
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
        // Registrar evento de desbloqueo
        self::logUnblock(
            $this->user,
            request()->ip() ?? 'unknown',
            $reason,
            $admin
        );
    }

    /**
     * Configuración de Activity Log (Spatie)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'user_id',
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
