<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Http\Data\GetTransactionResponseData;
use Modules\Transaction\Models\Transaction;

/**
 * Consulta transacciones del usuario autenticado.
 *
 * Todas las consultas están filtradas por el email del usuario extraído
 * del JWT, garantizando que cada usuario solo vea sus propios registros.
 */
readonly class GetTransactionAction
{
    /** @return array<int, GetTransactionResponseData> */
    public function getAll(GatewayUser $user): array
    {
        $transactions = Transaction::where('user_email', $user->email)->get();

        return GetTransactionResponseData::collect($transactions)->all();
    }

    /** @return array<int, GetTransactionResponseData> */
    public function getById(int $id, GatewayUser $user): array
    {
        $transaction = Transaction::where('user_email', $user->email)->where('id', $id)->get();

        return GetTransactionResponseData::collect($transaction)->all();
    }
}
