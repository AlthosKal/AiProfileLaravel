<?php

use Illuminate\Support\Facades\Route;
use Modules\Client\Http\LaravelMcp\Transaction\Controllers\TransactionController;
use Modules\Shared\Enums\MiddlewaresFramework;

Route::middleware([MiddlewaresFramework::with(MiddlewaresFramework::AUTH, 'sanctum')])->prefix('v1')->group(function () {
    Route::get('transactions/export', [TransactionController::class, 'export'])
        ->name('transactions.export');
    Route::post('transactions/import', [TransactionController::class, 'import'])
        ->name('transactions.import');
    Route::apiResource('transactions', TransactionController::class)
        ->names('api.transactions');
});
