<?php

use Illuminate\Support\Facades\Route;
use Modules\McpServer\Http\Controllers\McpServerController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('mcpservers', McpServerController::class)->names('mcpserver');
});
