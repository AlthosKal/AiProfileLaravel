<?php

use Illuminate\Support\Facades\Route;
use Modules\McpClient\Http\Controllers\McpClientController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('mcpclients', McpClientController::class)->names('mcpclient');
});
