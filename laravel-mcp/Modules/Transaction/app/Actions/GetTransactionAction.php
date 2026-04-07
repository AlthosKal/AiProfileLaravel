<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Http\Data\GetTransactionResponseData;
use Modules\Transaction\Models\Transaction;

readonly class GetTransactionAction
{
    /** @return array<int, GetTransactionResponseData> */
    public function getAll(): array
    {
        /** @var GatewayUser $user */
        $user = request()->user();
        $user_email = $user->email;

        $transactions = Transaction::where('user_email', $user_email)->get();

        return GetTransactionResponseData::collect($transactions)->all();
    }

    /** @return array<int, GetTransactionResponseData> */
    public function getById(int $id): array
    {
        /** @var GatewayUser $user */
        $user = request()->user();
        $user_email = $user->email;

        $transaction = Transaction::where('user_email', $user_email)->where('id', $id)->get();

        return GetTransactionResponseData::collect($transaction)->all();
    }
}
