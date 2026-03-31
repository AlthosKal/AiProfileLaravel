<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Auth\Http\Middleware\EnsureEmailIsVerified;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // EnsureFrontendRequestsAreStateful se omite intencionalmente:
        // este sistema usa Sanctum API tokens (stateless), no SPA cookie-based auth.
        // Los clientes autentican con `Authorization: Bearer {token}` en el header.

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Al ser una API pura, forzamos JSON en todas las rutas api/* independientemente
        // del header Accept del cliente, evitando respuestas HTML en errores no manejados.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    })->create();
