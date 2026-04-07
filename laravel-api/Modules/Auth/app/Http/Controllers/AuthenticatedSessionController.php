<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\Auth\LoginAction;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Http\Data\AuthenticatedSessionResponseData;
use Modules\Auth\Http\Data\LoginData;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly LoginAction $action
    ) {}

    /**
     * Autenticar al usuario y retornar el token Sanctum junto con el estado post-login.
     *
     * El token debe incluirse en el header `Authorization: Bearer {token}` en todos
     * los requests autenticados subsiguientes. Las flags post-login permiten al frontend
     * redirigir al desafío 2FA, a verificación de email, o mostrar advertencia de
     * contraseña próxima a vencer según corresponda.
     *
     * @throws Throwable
     */
    public function store(LoginData $data): JsonResponse
    {
        $result = AuthenticatedSessionResponseData::fromLoginResponse(
            $this->action->login($data, request()->ip())
        );

        return $this->success(AuthSuccessCode::LoginSuccess->value, $result);
    }

    /**
     * Revocar el token Sanctum del request actual.
     *
     * Con API token authentication el "logout" consiste en eliminar el token
     * de la tabla personal_access_tokens. Solo se revoca el token del dispositivo
     * actual — los tokens de otros dispositivos permanecen activos.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->userIsLogout($request->user()->email);
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
