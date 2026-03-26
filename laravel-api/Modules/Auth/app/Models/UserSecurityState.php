<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Builders\UserSecurityStateBuilder;

// La vista es solo de lectura
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSecurityState newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSecurityState newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSecurityState query()
 * @mixin \Eloquent
 */
#[Guarded(['*'])]
// El primary key no es auto-incremental (es UUID)
#[WithoutIncrementing]
// Como es una vista no cuenta con timestamps
#[WithoutTimestamps]
// Vista de PostgreSQL creada con una migración por lo que toca especificar nombre, ya que no se sigue la convención de nombres
#[Table('user_security_state')]

class UserSecurityState extends Model
{
    // Como Llave Primaria el Id del usuario
    protected $primaryKey = 'user_id';

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
     * Sobrescribir query() para retornar el Custom Builder
     *
     * Esto permite el tipado correcto para PHPStan sin necesidad de @method
     */
    public static function query(): UserSecurityStateBuilder
    {
        /** @var UserSecurityStateBuilder */
        return parent::query();
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
