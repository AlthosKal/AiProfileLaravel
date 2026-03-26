<?php

namespace Modules\Auth\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Auth\Enums\SecurityStatusEnum;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

// Indica que campos se pueden asignar masivamente, su alternativa el $guarden con el cual se indican cúales son los campos que no se pueden asígnar masivamente
/**
 * @property SecurityStatusEnum $security_status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Auth\Models\PasswordHistory> $passwordHistories
 * @property-read int|null $password_histories_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @mixin \Eloquent
 */
#[Fillable([
    'name',
    'email',
    'is_email_verified',
    'email_verified_at',
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'is_two_factor_confirmed',
    'two_factor_confirmed_at',
    'google_auth_enabled',
    'password_changed_at',
    'identification_number',
    'identification_type',
    'last_login_at',
    'last_logout_at',
    'security_status',
])]
// Indica que atributos se ocultan al convertir a array/JSON, su alternativa es $visible con el cual se indican cúales son los unicos campos que serán visibles al convertir a array/JSON
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasUuids, LogsActivity, Notifiable;

    // Los $appends agrega atributos calculados al JSON
    // Están también los accessors y mutators (getters y setters), los cuales cumplen el propósito de leer y guardar valores pero tambien de al momento de leerlos o guardarlos, transformarlos.
    // Attribute es la versión unificada de los accessors y mutators
    /**
     * Transforma atributos automaticamente
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'is_two_factor_confirmed' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'google_auth_enabled' => 'boolean',
            'last_login_at' => 'datetime',
            'last_logout_at' => 'datetime',
            'security_status' => SecurityStatusEnum::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'email';
    }

    /**
     * Trae el historial de contraseñas del usuario
     *
     * @return HasMany<PasswordHistory, $this>
     */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    // Revisa si ya expiro la contraseña actúal
    public function hasPasswordExpired(): bool
    {
        if (! $this->password_changed_at) {
            return false;
        }

        $expirationDays = config('auth.password_expiration_days', 30);

        return $this->password_changed_at->addDays($expirationDays)->isPast();
    }

    // Revisa cuantos días faltán para que expire la contraseña del usuario autenticado
    public function getDaysUntilPasswordExpires(): int
    {
        $expirationDays = config('auth.password_expiration_days', 30);

        if (! $this->password_changed_at) {
            return $expirationDays;
        }

        $expirationDate = $this->password_changed_at->addDays($expirationDays);

        // Cast explícito a int para evitar warning de conversión implícita
        return (int) max(0, now()->diffInDays($expirationDate, false));
    }

    // ============================================
    // Métodos de Instancia - Verificaciones de Estado
    // ============================================

    /**
     * Verificar si puede acceder al sistema
     * (debe estar activo manualmente Y no bloqueado)
     */
    public function canAccessSystem(): bool
    {
        return $this->is_active && ! $this->isBlocked();
    }

    /**
     * Verificar si está bloqueado temporalmente
     */
    public function isTemporarilyBlocked(): bool
    {
        return $this->security_status->isTemporarilyBlocked()
            && $this->blocked_until
            && $this->blocked_until->isFuture();
    }

    /**
     * Verificar si el bloqueo temporal ya expiró
     */
    public function hasExpiredBlock(): bool
    {
        return $this->security_status->isTemporarilyBlocked()
            && $this->blocked_until
            && $this->blocked_until->isPast();
    }

    /**
     * Configuración de auditoría con Spatie ActivityLog
     *
     * Registra cambios importantes del usuario para trazabilidad
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // Campos a auditar (NO incluir password hash por seguridad)
            ->logOnly([
                'name',
                'email',
                'is_email_verified',
                'email_verified_at',
                'password_changed_at',
                'security_status',
            ])
            ->logOnlyDirty() // Solo cambios reales
            ->dontSubmitEmptyLogs() // No crear logs vacíos
            ->setDescriptionForEvent(fn (string $eventName) => "Usuario {$eventName}")
            ->useLogName('user') // Nombre del log para filtrar
            // Agregar información contextual
            ->dontLogIfAttributesChangedOnly(['remember_token', 'updated_at']);
    }
}
