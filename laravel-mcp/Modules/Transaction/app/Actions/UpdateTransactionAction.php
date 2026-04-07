<?php

namespace Modules\Transaction\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Http\Data\AddOrUpdateTransactionData;
use Modules\Transaction\Models\Transaction;

/**
 * Actualiza una transacción existente del usuario autenticado.
 *
 * Verifica que la transacción pertenezca al usuario antes de modificarla
 * para evitar que un usuario edite registros ajenos.
 */
readonly class UpdateTransactionAction
{
    public function update(int $id, AddOrUpdateTransactionData $data, GatewayUser $user): void
    {
        $transaction = Transaction::where('id', $id)->where('user_email', $user->email)->first();

        if (! $transaction->exists()) {
            throw ValidationException::withMessages([
                'errorCode' => TransactionErrorCode::TransactionNotFound->value,
            ]);
        }

        $transaction->update($data->toArray());
    }
}
