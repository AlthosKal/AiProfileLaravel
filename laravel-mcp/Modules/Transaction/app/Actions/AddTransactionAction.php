<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Http\Data\AddOrUpdateTransactionData;
use Modules\Transaction\Models\Transaction;

readonly class AddTransactionAction
{
    public function add(AddOrUpdateTransactionData $data): void
    {
        /** @var GatewayUser $user */
        $user = request()->user();
        $user_email = $user->email;

        Transaction::create([
            'user_email' => $user_email,
            'name' => $data->name,
            'amount' => $data->amount,
            'description' => $data->description,
            'type' => $data->type,
        ]);

    }
}
