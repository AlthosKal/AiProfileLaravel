<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Enums\AuthErrorCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que protege rutas que requieren email verificado.
 *
 * Si el usuario no está autenticado o no ha verificado su email,
 * retorna 409 con la clave semántica `EmailVerificationRequired`
 * para que el frontend redirija al flujo de verificación correspondiente.
 *
 * Se usa 409 (Conflict) en lugar de 403 (Forbidden) para distinguir
 * un estado pendiente de verificación de un acceso denegado permanente.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasVerifiedEmail()) {
            return response()->json(AuthErrorCode::EmailVerificationRequired->value, 409);
        }

        return $next($request);
    }
}
