<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Shared\Security\GatewayUser;

Route::prefix('v1')->middleware('auth:jwt-gateway')->group(function () {
    Route::get('/user', function (Request $request) {
        /** @var GatewayUser $user */
        $user = $request->user();

        return response()->json(['email' => $user->email]);
    });
});
