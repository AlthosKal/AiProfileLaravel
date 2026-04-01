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
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken; // usado solo en PHPDoc para tipado de la relación tokens()
use Modules\Auth\Database\Factories\UserFactory;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Models\Concerns\HasActivityLog;
use Modules\Auth\Models\Concerns\HasPasswordExpiration;
use Modules\Auth\Models\Concerns\HasSecurityStatus;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $email
 * @property bool $is_two_factor_enabled
 * @property SecurityStatusEnum $security_status
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $blocked_until
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, PasswordHistory> $passwordHistories
 * @property-read int|null $password_histories_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property string $password
 *
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 *
 * @property string $id Identificador único del usuario dentro del sistema
 * @property string $name Nombre del usuario
 * @property bool $is_email_verified Indica si un usuario ya fue autenticado
 * @property Carbon|null $email_verified_at Fecha en la que el usuario ha sido verificado
 * @property string|null $two_factor_secret Almacena la clave secreta encriptada (TOTP) que se comparte con la app authenticator (Google Authenticator, Authy, etc.) para generar los códigos de 6 dígitos
 * @property string|null $two_factor_recovery_codes Almacena un array JSON de códigos de recuperación encriptados, usados cuando el usuario no tiene acceso a su app authenticator
 * @property Carbon|null $two_factor_confirmed_at Fecha de cuando el usuario confirmó/activó el 2FA
 * @property bool $google_auth_enabled Indica si el usuario puede autenticarse con Google
 * @property string|null $remember_token Token para mantener la sesión activa entre visitas ("Recordarme")
 * @property int|null $identification_number Número de identificación
 * @property string|null $identification_type Tipo de identificación
 * @property Carbon|null $last_login_at Ultimo login del usuario
 * @property Carbon|null $last_logout_at Ultimo logout
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereGoogleAuthEnabled($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIdentificationNumber($value)
 * @method static Builder<static>|User whereIdentificationType($value)
 * @method static Builder<static>|User whereIsEmailVerified($value)
 * @method static Builder<static>|User whereIsTwoFactorEnabled($value)
 * @method static Builder<static>|User whereLastLoginAt($value)
 * @method static Builder<static>|User whereLastLogoutAt($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePasswordChangedAt($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereSecurityStatus($value)
 * @method static Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static Builder<static>|User whereTwoFactorSecret($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
// Indica que campos se pueden asignar masivamente
#[Fillable([
    'name',
    'email',
    'email_verified_at',
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'is_two_factor_enabled',
    'two_factor_confirmed_at',
    'google_auth_enabled',
    'password_changed_at',
    'identification_number',
    'identification_type',
    'last_login_at',
    'last_logout_at',
    'security_status',
])]
// Indica que atributos se ocultan al convertir a array/JSON
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasActivityLog, HasPasswordExpiration, HasSecurityStatus;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, LogsActivity, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Transforma atributos automaticamente.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'is_two_factor_enabled' => 'boolean',
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

    public static function userAlreadyExists(string $email): bool
    {
        return static::where('email', $email)->exists();
    }

    /**
     * Trae el historial de contraseñas del usuario.
     *
     * @return HasMany<PasswordHistory, $this>
     */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class, 'user_email', 'email');
    }
}
