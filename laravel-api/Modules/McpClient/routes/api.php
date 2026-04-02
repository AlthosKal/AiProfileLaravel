<?php

use Illuminate\Support\Facades\Route;
use Modules\McpClient\Http\Controllers\McpClientController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('mcpclients', McpClientController::class)->names('mcpclient');
});
