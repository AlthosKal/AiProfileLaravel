<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Shared\Enums\ExportFormat;
use Modules\Transaction\Actions\AddTransactionAction;
use Modules\Transaction\Actions\DeleteTransactionAction;
use Modules\Transaction\Actions\ExportTransactionAction;
use Modules\Transaction\Actions\GetTransactionAction;
use Modules\Transaction\Actions\ImportTransactionAction;
use Modules\Transaction\Actions\UpdateTransactionAction;
use Modules\Transaction\Enums\TransactionSuccessCode;
use Modules\Transaction\Http\Data\AddOrUpdateTransactionData;
use Modules\Transaction\Http\Requests\ExportTransactionRequest;
use Modules\Transaction\Http\Requests\ImportTransactionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly GetTransactionAction $getAction,
        private readonly AddTransactionAction $addAction,
        private readonly UpdateTransactionAction $updateAction,
        private readonly DeleteTransactionAction $deleteAction,
        private readonly ExportTransactionAction $exportAction,
        private readonly ImportTransactionAction $importAction,
    ) {}

    public function index(): JsonResponse
    {
        $result = $this->getAction->getAll();

        return $this->success(status: TransactionSuccessCode::TransactionListedSuccessfully->value, data: $result);
    }

    public function store(AddOrUpdateTransactionData $data): JsonResponse
    {
        $this->addAction->add($data);

        return $this->success(status: TransactionSuccessCode::TransactionCreatedSuccessfully->value, httpStatus: Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->getAction->getById($id);

        return $this->success(status: TransactionSuccessCode::TransactionListedSuccessfully->value, data: $result);
    }

    public function update(int $id, AddOrUpdateTransactionData $data): JsonResponse
    {
        $this->updateAction->update($id, $data);

        return $this->success(status: TransactionSuccessCode::TransactionUpdatedSuccessfully->value, httpStatus: Response::HTTP_CREATED);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->deleteAction->delete($id);

        return $this->success(status: TransactionSuccessCode::TransactionDeletedSuccessfully->value, httpStatus: Response::HTTP_NO_CONTENT);
    }

    public function export(ExportTransactionRequest $request): BinaryFileResponse
    {
        $format = ExportFormat::from($request->validated('format'));

        return $this->exportAction->export($format);
    }

    public function import(ImportTransactionRequest $request): JsonResponse
    {
        $format = ExportFormat::from($request->validated('format'));

        $this->importAction->import($format, $request->file('file'));

        return $this->success(status: TransactionSuccessCode::TransactionsImportedSuccessfully->value, httpStatus: Response::HTTP_CREATED);
    }
}
