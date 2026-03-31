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
 *
 * Retorna siempre JSON con clave semántica dado que este sistema
 * es una API pura.
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * Reenviar la notificación de verificación de email.
     *
     * Si el email ya está verificado retorna EmailAlreadyVerified para evitar
     * envíos innecesarios. De lo contrario despacha la notificación y retorna
     * VerificationLinkSent como confirmación al frontend.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->success(AuthSuccessCode::EmailAlreadyVerified->value);
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->success(AuthSuccessCode::VerificationLinkSent->value);
    }
}
