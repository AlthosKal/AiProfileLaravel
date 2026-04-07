<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shared\Enums\ExportFormat;
use Modules\Transaction\Actions\AddTransactionAction;
use Modules\Transaction\Actions\DeleteTransactionAction;
use Modules\Transaction\Actions\ExportTransactionAction;
use Modules\Transaction\Actions\GetTransactionsAction;
use Modules\Transaction\Actions\ImportTransactionAction;
use Modules\Transaction\Actions\UpdateTransactionAction;
use Modules\Transaction\Enums\TransactionSuccessCode;
use Modules\Transaction\Http\Data\AddOrUpdateTransactionData;
use Modules\Transaction\Http\Requests\ExportTransactionRequest;
use Modules\Transaction\Http\Requests\ImportTransactionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador REST para el módulo de transacciones.
 *
 * Todas las rutas están protegidas por el guard jwt-gateway, por lo que
 * $request->user() siempre retorna un GatewayUser con el email del JWT.
 * Cada operación está delegada a su Action correspondiente.
 */
class TransactionController extends Controller
{
    public function __construct(
        private readonly GetTransactionsAction $getAction,
        private readonly AddTransactionAction $addAction,
        private readonly UpdateTransactionAction $updateAction,
        private readonly DeleteTransactionAction $deleteAction,
        private readonly ExportTransactionAction $exportAction,
        private readonly ImportTransactionAction $importAction,
    ) {}

    /**
     * Retorna todas las transacciones del usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->getAction->getAll(
            user: $user,
            perPage: (int) $request->query('per_page', 15),
            page: (int) $request->query('page', 1),
        );

        return $this->success(status: TransactionSuccessCode::TransactionListedSuccessfully->value, data: $result);
    }

    /**
     * Crea una nueva transacción para el usuario autenticado.
     */
    public function store(AddOrUpdateTransactionData $data, Request $request): JsonResponse
    {
        $user = $request->user();
        $this->addAction->add($data, $user);

        return $this->success(status: TransactionSuccessCode::TransactionCreatedSuccessfully->value, httpStatus: Response::HTTP_CREATED);
    }

    /**
     * Retorna una transacción específica del usuario autenticado.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->getAction->getById($id, $user);

        return $this->success(status: TransactionSuccessCode::TransactionListedSuccessfully->value, data: $result);
    }

    /**
     * Actualiza una transacción existente del usuario autenticado.
     */
    public function update(int $id, AddOrUpdateTransactionData $data, Request $request): JsonResponse
    {
        $user = $request->user();
        $this->updateAction->update($id, $data, $user);

        return $this->success(status: TransactionSuccessCode::TransactionUpdatedSuccessfully->value, httpStatus: Response::HTTP_CREATED);
    }

    /**
     * Elimina una transacción del usuario autenticado.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $this->deleteAction->delete($id, $user);

        return $this->success(status: TransactionSuccessCode::TransactionDeletedSuccessfully->value, httpStatus: Response::HTTP_NO_CONTENT);
    }

    /**
     * Exporta las transacciones del usuario en el formato y rango de fechas indicados.
     */
    public function export(ExportTransactionRequest $export): BinaryFileResponse
    {
        $format = ExportFormat::from($export->validated('format'));

        return $this->exportAction->export($format, $export->validated('date_from'), $export->validated('date_to'));
    }

    /**
     * Importa transacciones desde un archivo en el formato indicado.
     */
    public function import(ImportTransactionRequest $import, Request $request): JsonResponse
    {
        $user = $request->user();
        $format = ExportFormat::from($import->validated('format'));

        $this->importAction->import($format, $import->file('file'), $user);

        return $this->success(status: TransactionSuccessCode::TransactionsImportedSuccessfully->value, httpStatus: Response::HTTP_CREATED);
    }
}
