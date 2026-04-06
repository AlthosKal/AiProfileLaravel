<?php

namespace Modules\Transaction\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Models\Transaction;

class DeleteTransactionAction
{
    public function delete(int $id): void
    {
        $user_email = request()->user()->email;
        $transaction = Transaction::where('id', $id)->where('user_email', $user_email);
        if(!$transaction->exists())
        {
            throw ValidationException::withMessages([
                'errorCode' => TransactionErrorCode::TransactionNotFound->value
            ]);
        }
        $transaction->delete();
    }
}
