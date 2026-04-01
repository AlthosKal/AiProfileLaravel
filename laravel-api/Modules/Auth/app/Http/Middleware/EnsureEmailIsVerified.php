<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Enums\AuthStatusCode;
use Modules\Shared\Traits\JsonResponseTrait;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * MiddlewaresFramework que protege rutas que requieren email verificado.
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
    use JsonResponseTrait;

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (! $request->user() || ! $request->user()->hasVerifiedEmail()) {
            return $this->error(AuthStatusCode::EmailVerificationRequired->value, Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}
