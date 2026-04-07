<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\Enums\MiddlewaresFramework;
use Modules\Transaction\Http\Controllers\TransactionController;

Route::prefix('v1')->middleware([MiddlewaresFramework::with(MiddlewaresFramework::AUTH, 'jwt-gateway')])->group(function () {
    Route::get('transactions/export', [TransactionController::class, 'export'])
        ->name('transaction.export');
    Route::post('transactions/import', [TransactionController::class, 'import'])
        ->name('transaction.import');
    Route::apiResource('transactions', TransactionController::class)
        ->names('api.transactions');
});
