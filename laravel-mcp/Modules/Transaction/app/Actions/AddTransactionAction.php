<?php

namespace Modules\Transaction\Actions;

use Modules\Transaction\Http\Data\AddTransactionData;
use Modules\Transaction\Models\Transaction;

class AddTransactionAction
{
    public function add(AddTransactionData $data): void
    {
        $user_email = request()->user()->email;

        Transaction::create([
            'user_email' => $user_email,
            'name' => $data->name,
            'amount' => $data->amount,
            'description' => $data->description,
            'type' => $data->type,
        ]);


    }
}
