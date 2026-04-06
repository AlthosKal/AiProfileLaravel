<?php

namespace Modules\Transaction\Actions;


use Modules\Transaction\Http\Data\GetTransactionResponseData;
use Modules\Transaction\Models\Transaction;

class GetTransactionAction
{
    public function get(): array
    {
        $user_email = request()->user()->email;

        $transaction = Transaction::where('user_email', $user_email)
            ->get();


        return GetTransactionResponseData::collect($transaction);
    }
}
