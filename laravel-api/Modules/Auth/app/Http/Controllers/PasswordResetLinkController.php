<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\Password\SendPasswordResetLinkAction;
use Modules\Auth\Http\Data\PasswordResetLinkData;

/**
 * Controlador para la solicitud de link de recuperación de contraseña.
 *
 * Thin controller: delega toda la lógica a SendPasswordResetLinkAction.
 * La validación del request ocurre automáticamente al resolver
 * PasswordResetLinkData como parámetro (Spatie LaravelData).
 */
class PasswordResetLinkController extends Controller
{
    public function __construct(
        private readonly SendPasswordResetLinkAction $action
    ) {}

    /**
     * Enviar el link de recuperación de contraseña al email indicado.
     */
    public function store(PasswordResetLinkData $data): JsonResponse
    {
        $status = $this->action->send($data);

        return $this->success($status);
    }
}
