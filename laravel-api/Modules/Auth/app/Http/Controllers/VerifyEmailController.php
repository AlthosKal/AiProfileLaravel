<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Enums\AuthSuccessCode;

/**
 * Controlador para la verificación de email mediante enlace firmado.
 *
 * No usa EmailVerificationRequest porque ese FormRequest resuelve el usuario
 * con el guard 'auth' (session-based), incompatible con auth:sanctum (token-based).
 * En su lugar se valida el id y hash directamente contra $request->user(),
 * que ya fue resuelto por el middleware auth:sanctum antes de llegar aquí.
 *
 * El middleware 'signed' en la ruta garantiza que la URL no fue manipulada.
 * La validación del id+hash previene que un usuario verifique el email de otro
 * aunque tenga una URL firmada válida.
 *
 * Retorna siempre JSON con clave semántica dado que este sistema
 * es una API pura — no hay redirecciones al frontend.
 */
class VerifyEmailController extends Controller
{
    /**
     * Marcar el email del usuario autenticado como verificado.
     *
     * Si el id o hash no coinciden con el usuario autenticado se retorna 403.
     * Si el email ya está verificado retorna EmailAlreadyVerified para que el
     * frontend pueda diferenciar la verificación nueva de un intento duplicado.
     */
    public function __invoke(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        if (! hash_equals((string) $user->getKey(), (string) $request->route('id'))) {
            return response()->noContent(Response::HTTP_FORBIDDEN);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return response()->noContent(Response::HTTP_FORBIDDEN);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(AuthSuccessCode::EmailAlreadyVerified->value);
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return $this->success(AuthSuccessCode::EmailVerified->value);
    }
}
