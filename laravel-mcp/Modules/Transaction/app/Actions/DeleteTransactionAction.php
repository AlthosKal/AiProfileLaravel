<?php

namespace Modules\Transaction\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Models\Transaction;

readonly class DeleteTransactionAction
{
    public function delete(int $id): void
    {
        /** @var GatewayUser $user */
        $user = request()->user();
        $user_email = $user->email;
        $transaction = Transaction::where('id', $id)->where('user_email', $user_email);
        if (! $transaction->exists()) {
            throw ValidationException::withMessages([
                'errorCode' => TransactionErrorCode::TransactionNotFound->value,
            ]);
        }
        $transaction->delete();
    }
}
