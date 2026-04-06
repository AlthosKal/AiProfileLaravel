<?php

namespace Modules\Transaction\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Http\Data\UpdateTransactionData;
use Modules\Transaction\Models\Transaction;

class UpdateTransactionAction
{
    public function update(UpdateTransactionData $data): void
    {
        $user_email = request()->user()->email;

        $transaction = Transaction::where('id', $data->id)->where('user_email', $user_email)->first();
        if(!$transaction->exists())
        {
            throw ValidationException::withMessages([
                'errorCode' => TransactionErrorCode::TransactionNotFound->value
            ]);
        }
        $transaction->update([
            'name' => $data->name,
            'amount' => $data->amount,
            'description' => $data->description,
            'type' => $data->type,
        ]);
    }
}
