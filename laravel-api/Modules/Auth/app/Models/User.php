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
 *
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 *
 * @mixin Eloquent
 */
// Indica que campos se pueden asignar masivamente
#[Fillable([
    'name',
    'email',
    'is_email_verified',
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
    use HasFactory, HasUuids, LogsActivity, Notifiable;

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

    /**
     * Trae el historial de contraseñas del usuario.
     *
     * @return HasMany<PasswordHistory, $this>
     */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }
}
