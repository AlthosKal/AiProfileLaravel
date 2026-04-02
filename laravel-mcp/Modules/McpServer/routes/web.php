<?php

use Illuminate\Support\Facades\Route;
use Modules\McpServer\Http\Controllers\McpServerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('mcpservers', McpServerController::class)->names('mcpserver');
});
