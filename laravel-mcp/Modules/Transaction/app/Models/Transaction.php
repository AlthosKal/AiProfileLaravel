<?php

namespace Modules\Transaction\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Database\Factories\TransactionFactory;
use Modules\Transaction\Enums\TransactionType;

/**
 * @property int $id
 * @property string $user_email
 * @property string $name
 * @property float $amount
 * @property string $description
 * @property string $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static TransactionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Transaction newModelQuery()
 * @method static Builder<static>|Transaction newQuery()
 * @method static Builder<static>|Transaction query()
 * @method static Builder<static>|Transaction forUser(GatewayUser $user)
 * @method static Builder<static>|Transaction whereAmount($value)
 * @method static Builder<static>|Transaction whereCreatedAt($value)
 * @method static Builder<static>|Transaction whereDescription($value)
 * @method static Builder<static>|Transaction whereId($value)
 * @method static Builder<static>|Transaction whereName($value)
 * @method static Builder<static>|Transaction whereType($value)
 * @method static Builder<static>|Transaction whereUpdatedAt($value)
 * @method static Builder<static>|Transaction whereUserEmail($value)
 *
 * @mixin Eloquent
 */
#[Fillable([
    'user_email',
    'name',
    'amount',
    'description',
    'type',
    'created_at',
    'updated_at',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    /**
     * Transforma atributos automáticamente.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'user_email';
    }

    /**
     * Filtra las transacciones del usuario autenticado y selecciona los campos relevantes.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeForUser(Builder $query, GatewayUser $user): void
    {
        $query->select(['name', 'amount', 'description', 'type', 'created_at', 'updated_at'])
            ->where('user_email', $user->email);
    }
}
