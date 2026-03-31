<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\Password\ResetPasswordAction;
use Modules\Auth\Http\Data\ResetPasswordData;
use Throwable;

/**
 * Controlador para completar el reset de contraseña con el token recibido por email.
 *
 * Thin controller: delega toda la lógica a ResetPasswordAction.
 * La validación del request ocurre automáticamente al resolver
 * ResetPasswordData como parámetro (Spatie LaravelData).
 */
class NewPasswordController extends Controller
{
    /**
     * Completar el reset de contraseña con el token y la nueva contraseña.
     *
     * @throws Throwable
     */
    public function store(ResetPasswordData $data, ResetPasswordAction $action): JsonResponse
    {
        $status = $action->update($data);

        return $this->success($status);
    }
}
