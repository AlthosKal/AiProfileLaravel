<?php

namespace Modules\Transaction\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Enums\TransactionErrorCode;
use Modules\Transaction\Http\Data\AddOrUpdateTransactionData;
use Modules\Transaction\Models\Transaction;

readonly class UpdateTransactionAction
{
    public function update(int $id, AddOrUpdateTransactionData $data): void
    {
        /** @var GatewayUser $user */
        $user = request()->user();
        $user_email = $user->email;

        $transaction = Transaction::where('id', $id)->where('user_email', $user_email)->first();
        if (! $transaction->exists()) {
            throw ValidationException::withMessages([
                'errorCode' => TransactionErrorCode::TransactionNotFound->value,
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
