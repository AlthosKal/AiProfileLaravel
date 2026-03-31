<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Enums\AuthSuccessCode;

/**
 * Controlador para el reenvío del email de verificación.
 *
 * Thin controller: no delega a una Action porque la lógica de reenvío
 * es trivial — Laravel la provee directamente en el modelo a través
 * del contrato MustVerifyEmail vía sendEmailVerificationNotification().
 * La throttle de 6 intentos por minuto se aplica en la definición
 * de la ruta (ver routes/auth.php).
 * La verificación de email previo está delegada al middleware EnsureEmailIsNotVerified.
 */
class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return $this->success(AuthSuccessCode::VerificationLinkSent->value);
    }
}
