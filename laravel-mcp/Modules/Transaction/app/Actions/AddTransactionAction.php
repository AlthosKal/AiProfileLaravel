<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Http\Data\AddOrUpdateTransactionData;
use Modules\Transaction\Models\Transaction;

/**
 * Crea una nueva transacción asociada al usuario autenticado.
 *
 * El email del usuario se extrae del GatewayUser (JWT) y se combina
 * con los datos del DTO para construir el registro en la base de datos.
 */
readonly class AddTransactionAction
{
    public function add(AddOrUpdateTransactionData $data, GatewayUser $user): void
    {
        Transaction::create([
            'user_email' => $user->email,
            ...$data->toArray(),
        ]);
    }
}
