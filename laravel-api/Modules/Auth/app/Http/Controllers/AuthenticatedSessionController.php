<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Actions\Auth\LoginAction;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Http\Data\AuthenticatedSessionResponseData;
use Modules\Auth\Http\Data\LoginData;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     *
     * Retorna un JSON con las flags de estado post-login para que el frontend
     * pueda redirigir al desafío 2FA, a verificación de email, o mostrar
     * una advertencia de contraseña próxima a vencer según corresponda.
     *
     * @throws Throwable
     */
    public function store(LoginData $data, LoginAction $action): JsonResponse
    {
        $result = AuthenticatedSessionResponseData::fromLoginResponse(
            $action->login($data, request()->ip())
        );

        request()->session()->regenerate();

        return $this->success(AuthSuccessCode::LoginSuccess->value, $result);
    }

    /**
     * Destroy an authenticated session.
     *
     * Con Sanctum SPA authentication la sesión vive en una cookie.
     * `invalidate()` la destruye completamente, y `regenerateToken()`
     * rota el token CSRF para que el SPA no pueda reutilizar el anterior.
     */
    public function destroy(): Response
    {
        Auth::logout();

        request()->session()->invalidate();

        request()->session()->regenerateToken();

        return response()->noContent();
    }
}
