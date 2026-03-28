<?php

namespace Modules\Auth\Models;

use Eloquent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Modules\Auth\Database\Factories\UserFactory;
use Modules\Auth\Enums\SecurityStatusEnum;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

// Indica que campos se pueden asignar masivamente, su alternativa el $guarden con el cual se indican cúales son los campos que no se pueden asígnar masivamente
/**
 * @property SecurityStatusEnum $security_status
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, PasswordHistory> $passwordHistories
 * @property-read int|null $password_histories_count
 *
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 *
 * @mixin Eloquent
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
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, LogsActivity, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    // Los $appends agrega atributos calculados al JSON
    // Están también los accessors y mutators (getters y setters), los cuales cumplen el propósito de leer y guardar valores pero también de al momento de leerlos o guardarlos, transformarlos.
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
        return (int) max(0, now()->diffInDays($expirationDate));
    }

    // ============================================
    // Métodos de Instancia - Verificaciones de Estado
    // ============================================

    /**
     * Verificar si está bloqueado (temporal o permanente)
     */
    public function isBlocked(): bool
    {
        return $this->security_status->isBlocked();
    }

    /**
     * Verificar si está bloqueado permanentemente
     */
    public function isPermanentlyBlocked(): bool
    {
        return $this->security_status->isPermanentlyBlocked();
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
            ->setDescriptionForEvent(fn (string $eventName) => "Usuario $eventName")
            ->useLogName('user') // Nombre del log para filtrar
            // Agregar información contextual
            ->dontLogIfAttributesChangedOnly(['remember_token', 'updated_at']);
    }
}
