<?php

namespace Modules\Transaction\Actions;

use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Http\Data\GetTransactionResponseData;
use Modules\Transaction\Http\Data\GetTransactionsByAmountRangeRequestData;
use Modules\Transaction\Http\Data\GetTransactionsByConditionResponseData;
use Modules\Transaction\Http\Data\GetTransactionsByPeriodRequestData;
use Modules\Transaction\Models\Transaction;

/**
 * Consulta transacciones del usuario autenticado.
 *
 * Todas las consultas están filtradas por el email del usuario extraído
 * del JWT, garantizando que cada usuario solo vea sus propios registros.
 */
readonly class GetTransactionsAction
{
    /**
     * @return array{
     *     data: array<int, GetTransactionResponseData>,
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     last_page: int,
     * }
     */
    public function getAll(GatewayUser $user, int $perPage = 15, int $page = 1): array
    {
        $paginator = Transaction::where('user_email', $user->email)
            ->paginate(perPage: $perPage, page: $page);

        return [
            'data' => array_map(fn ($item) => GetTransactionResponseData::from($item), $paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /** @return array<int, GetTransactionResponseData> */
    public function getById(int $id, GatewayUser $user): array
    {
        $query = Transaction::where('user_email', $user->email)->where('id', $id)->get();

        return GetTransactionResponseData::collect($query)->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getByPeriod(GetTransactionsByPeriodRequestData $data, GatewayUser $user): array
    {
        $query = Transaction::select(['name', 'amount', 'description', 'type', 'created_at', 'updated_at'])
            ->where('user_email', $user->email)
            ->whereDate('created_at', '>=', $data->date_from)
            ->whereDate('created_at', '<=', $data->date_to)
            ->get();

        return GetTransactionsByConditionResponseData::from([
            'transaction_data' => $query,
            'transaction_count' => $query->count(),
            'total_amount' => $query->sum('amount'),
        ])->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getByAmountRange(GetTransactionsByAmountRangeRequestData $data, GatewayUser $user): array
    {
        $query = Transaction::select(['name', 'amount', 'description', 'type', 'created_at', 'updated_at'])
            ->where('user_email', $user->email)
            ->where('amount', '>=', $data->amount_from)
            ->where('amount', '<=', $data->amount_to)
            ->get();

        return GetTransactionsByConditionResponseData::from([
            'transaction_data' => $query,
            'transaction_count' => $query->count(),
            'total_amount' => $query->sum('amount'),
        ])->toArray();
    }
}
