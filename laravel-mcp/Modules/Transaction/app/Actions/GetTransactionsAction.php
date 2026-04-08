<?php

namespace Modules\Transaction\Actions;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Http\Data\Request\GetTransactionsByAmountRangeRequestData;
use Modules\Transaction\Http\Data\Request\GetTransactionsByMcpRequestData;
use Modules\Transaction\Http\Data\Request\GetTransactionsByPeriodRequestData;
use Modules\Transaction\Http\Data\Request\GetTransactionsByTypeRequestData;
use Modules\Transaction\Http\Data\Response\GetTransactionResponseData;
use Modules\Transaction\Http\Data\Response\GetTransactionsByConditionResponseData;
use Modules\Transaction\Http\Data\Response\GetTransactionsPaginatedData;
use Modules\Transaction\Http\Data\TransactionData;
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
     * @return array<string, mixed>
     */
    public function getAll(GatewayUser $user, int $perPage = 15, int $page = 1): array
    {
        $paginator = Transaction::where('user_email', $user->email)
            ->paginate(perPage: $perPage, page: $page);

        return GetTransactionsPaginatedData::from(
            data: array_map(fn ($item) => GetTransactionResponseData::from($item), $paginator->items()),
            total: $paginator->total(),
            per_page: $paginator->perPage(),
            current_page: $paginator->currentPage(),
            last_page: $paginator->lastPage(),
        )->toArray();
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
        $query = Transaction::forUser($user)
            ->whereDate('created_at', '>=', $data->date_from)
            ->whereDate('created_at', '<=', $data->date_to)
            ->get();

        return $this->buildConditionResult($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function getByAmountRange(GetTransactionsByAmountRangeRequestData $data, GatewayUser $user): array
    {
        $query = Transaction::forUser($user)
            ->where('amount', '>=', $data->amount_from)
            ->where('amount', '<=', $data->amount_to)
            ->get();

        return $this->buildConditionResult($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function getByType(GetTransactionsByTypeRequestData $data, GatewayUser $user): array
    {
        $paginator = Transaction::forUser($user)
            ->where('type', $data->type)
            ->paginate(perPage: $data->per_page, page: $data->page);

        return $this->buildPaginatedResult($paginator);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllByMcp(GetTransactionsByMcpRequestData $data, GatewayUser $user): array
    {
        $paginator = Transaction::forUser($user)
            ->paginate(perPage: $data->per_page, page: $data->page);

        return $this->buildPaginatedResult($paginator);
    }

    /**
     * @param  LengthAwarePaginator<int, Transaction>  $paginator
     * @return array<string, mixed>
     */
    private function buildPaginatedResult(LengthAwarePaginator $paginator): array
    {
        return GetTransactionsPaginatedData::from(
            data: array_map(fn ($item) => TransactionData::from($item), $paginator->items()),
            total: $paginator->total(),
            total_amount: $paginator->getCollection()->sum('amount'),
            per_page: $paginator->perPage(),
            current_page: $paginator->currentPage(),
            last_page: $paginator->lastPage(),
        )->toArray();
    }

    /**
     * @param  Collection<int, Transaction>  $query
     * @return array<string, mixed>
     */
    private function buildConditionResult(Collection $query): array
    {
        return GetTransactionsByConditionResponseData::from(
            data: TransactionData::collect($query),
            transaction_count: $query->count(),
            total_amount: $query->sum('amount'),
        )->toArray();
    }
}
