<?php

namespace Modules\Transaction\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Transaction\Database\Factories\TransactionFactory;

/**
 * @method static TransactionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Transaction newModelQuery()
 * @method static Builder<static>|Transaction newQuery()
 * @method static Builder<static>|Transaction query()
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
    use HasFactory;

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    /**
     * Transforma atributos automaticamente.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'user_email';
    }
}
