<?php

namespace Modules\Auth\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @method static Builder<static>|PasswordHistory newModelQuery()
 * @method static Builder<static>|PasswordHistory newQuery()
 * @method static Builder<static>|PasswordHistory query()
 *
 * @property int $id
 * @property string $user_email
 * @property string $password Contraseña prevía del usuario registrado en el sistema
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|PasswordHistory whereCreatedAt($value)
 * @method static Builder<static>|PasswordHistory whereId($value)
 * @method static Builder<static>|PasswordHistory wherePassword($value)
 * @method static Builder<static>|PasswordHistory whereUpdatedAt($value)
 * @method static Builder<static>|PasswordHistory whereUserEmail($value)
 *
 * @mixin Eloquent
 */
#[Fillable(['user_email', 'password'])]
class PasswordHistory extends Model
{
    public function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
