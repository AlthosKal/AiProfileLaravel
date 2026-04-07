<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\Auth\RegisterUserAction;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Http\Data\RegisterUserData;
use Symfony\Component\HttpFoundation\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $action,
    ) {}

    /**
     * Registrar un nuevo usuario en el sistema.
     *
     * La validación ocurre automáticamente al resolver RegisterUserData como
     * parámetro del método — si falla, Spatie Data lanza una ValidationException
     * antes de llegar a este método.
     *
     * Retorna 201 Created sin datos adicionales: el usuario debe verificar su
     * email antes de poder autenticarse.
     */
    public function store(RegisterUserData $data): JsonResponse
    {
        $this->action->register($data);

        return $this->success(status: AuthSuccessCode::RegisterSuccess->value, httpStatus: Response::HTTP_CREATED);
    }
}
