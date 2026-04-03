<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:jwt-gateway')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json(['email' => $request->user()->email]);
    });
});
