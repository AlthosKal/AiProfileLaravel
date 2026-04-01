<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Shared\Traits\JsonResponseTrait;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * MiddlewaresFramework que cortocircuita rutas de verificación cuando el email ya está verificado.
 *
 * Retorna 200 con la clave semántica `EmailAlreadyVerified` en lugar de continuar
 * al controlador, evitando reenvíos innecesarios y marcados duplicados.
 *
 * Se diferencia de EnsureEmailIsVerified en que este middleware protege flujos
 * de verificación (no debe procesarse si ya está hecho), mientras que el otro
 * protege recursos que requieren email verificado para acceder.
 */
class EnsureEmailIsNotVerified
{
    use JsonResponseTrait;

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return $this->success(AuthSuccessCode::EmailAlreadyVerified->value);
        }

        return $next($request);
    }
}
