<?php

namespace Modules\Auth\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Modules\Auth\Builders\UserSecurityStateBuilder;

// La vista es solo de lectura
/**
 * @property string $security_status
 * @property Carbon|null $blocked_until
 * @property bool $is_active
 *
 * @method static UserSecurityStateBuilder newModelQuery()
 * @method static UserSecurityStateBuilder newQuery()
 * @method static UserSecurityStateBuilder query()
 *
 * @property string|null $user_email
 * @property string|null $name
 * @property string|null $blocked_reason
 * @property Carbon|null $blocked_at
 * @property string|null $blocked_from_ip
 * @property int|null $lockout_count
 *
 * @method static UserSecurityStateBuilder whereBlockedAt($value)
 * @method static UserSecurityStateBuilder whereBlockedFromIp($value)
 * @method static UserSecurityStateBuilder whereBlockedReason($value)
 * @method static UserSecurityStateBuilder whereBlockedUntil($value)
 * @method static UserSecurityStateBuilder whereLockoutCount($value)
 * @method static UserSecurityStateBuilder whereName($value)
 * @method static UserSecurityStateBuilder whereSecurityStatus($value)
 * @method static UserSecurityStateBuilder whereUserEmail($value)
 * @method static UserSecurityStateBuilder expiredBlocks()
 * @method static UserSecurityStateBuilder onlyBlocked()
 * @method static UserSecurityStateBuilder orderByRecent()
 * @method static UserSecurityStateBuilder search(string $term)
 * @method static UserSecurityStateBuilder withTimestamps()
 * @method static array{total_users: int, normal_users: int, temp_blocked_users: int, perm_blocked_users: int, manually_deactivated_users: int, avg_lockout_count: float} getSecurityStats()
 *
 * @mixin Eloquent
 */
#[Guarded(['*'])]
// El primary key no es auto-incremental
#[WithoutIncrementing]
// Como es una vista no cuenta con timestamps
#[WithoutTimestamps]
// Vista de PostgreSQL creada con una migración por lo que toca especificar nombre, ya que no se sigue la convención de nombres
#[Table('user_security_state')]

class UserSecurityState extends Model
{
    // Como Llave Primaria el ID del usuario
    protected $primaryKey = 'user_email';

    // Tipo de Primary Key
    public $keyType = 'string';

    /**
     * Casts de atributos
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
            'blocked_until' => 'datetime',
            'lockout_count' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Registrar el Custom Builder para que Eloquent lo use en todas las queries
     *
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder($query): UserSecurityStateBuilder
    {
        return new UserSecurityStateBuilder($query);
    }

    // ============================================
    // Métodos Helper - Verificaciones de Estado
    // ============================================

    // Verificar si está bloqueado actualmente
    public function isBlocked(): bool
    {
        return in_array($this->security_status, ['temporarily_blocked', 'permanently_blocked'], true);
    }

    /**
     * Verificar si está bloqueado temporalmente
     */
    public function isTemporarilyBlocked(): bool
    {
        return $this->security_status === 'temporarily_blocked'
            && $this->blocked_until
            && $this->blocked_until->isFuture();
    }

    /**
     * Verificar si está bloqueado permanentemente
     */
    public function isPermanentlyBlocked(): bool
    {
        return $this->security_status === 'permanently_blocked';
    }

    /**
     * Verificar si puede acceder al sistema
     */
    public function canAccessSystem(): bool
    {
        return $this->is_active && ! $this->isBlocked();
    }
}
