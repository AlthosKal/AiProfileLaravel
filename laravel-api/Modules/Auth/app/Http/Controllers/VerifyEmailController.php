<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Http\Requests\VerifyEmailRequest;

/**
 * Controlador para la verificación de email mediante enlace firmado.
 *
 * La autorización del par id+hash está delegada a VerifyEmailRequest.
 * La verificación de email previo está delegada al middleware EnsureEmailIsNotVerified.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(VerifyEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->markEmailAsVerified();

        event(new Verified($user));

        return $this->success(AuthSuccessCode::EmailVerified->value);
    }
}
