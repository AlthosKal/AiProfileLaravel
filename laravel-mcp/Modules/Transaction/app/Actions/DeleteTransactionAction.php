<?php

namespace Modules\Transaction\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Models\Transaction;

/**
 * Elimina una transacción del usuario autenticado.
 *
 * Verifica que la transacción pertenezca al usuario antes de eliminarla
 * para evitar que un usuario borre registros ajenos.
 */
readonly class DeleteTransactionAction
{
    public function delete(int $id, GatewayUser $user): void
    {
        $transaction = Transaction::where('id', $id)->where('user_email', $user->email);

        if (! $transaction->exists()) {
            throw ValidationException::withMessages([
                'errorCode' => TransactionErrorCode::TransactionNotFound->value,
            ]);
        }

        $transaction->delete();
    }
}
