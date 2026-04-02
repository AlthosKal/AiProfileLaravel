<?php

namespace Modules\Auth\Actions\OAuth\Google;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Modules\Auth\Actions\Password\SendPasswordResetLinkAction;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\PasswordResetReason;
use Modules\Auth\Helpers\CheckAccountBlockStatusHelper;
use Modules\Auth\Http\Data\GoogleAuthData;
use Modules\Auth\Http\Data\PasswordResetLinkData;
use Modules\Auth\Interfaces\OAuth\CallbackStrategyInterface;
use Modules\Auth\Interfaces\OAuth\RedirectStrategyInterface;
use Modules\Auth\Models\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

readonly class GoogleOAuthAction implements CallbackStrategyInterface, RedirectStrategyInterface
{
    public function __construct(
        private CheckAccountBlockStatusHelper $statusHelper,
        private SendPasswordResetLinkAction $sendLinkAction,
    ) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Procesar el callback de Google, validar el usuario y redirigir al frontend con el JWT.
     *
     * Flujo de validaciones en orden de precedencia:
     *   1. Verificar que el usuario existe en el sistema
     *   2. Verificar que el usuario tiene Google Auth habilitado
     *   3. Verificar bloqueos de la cuenta
     *   4. Verificar si la contraseña está vencida
     *
     * @throws ValidationException
     */
    public function callback(): RedirectResponse
    {
        $socialiteUser = Socialite::driver('google')->user();
        $data = GoogleAuthData::fromSocialiteUser($socialiteUser);

        $user = User::where('email', $data->email)->first();

        // 1. Verificar que el usuario existe en el sistema.
        //    Si no existe se rechaza — el registro debe hacerse por el flujo normal.
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => AuthErrorCode::UserNotFound->value,
            ]);
        }

        // 2. Verificar que el usuario tiene Google Auth habilitado.
        $this->checkGoogleAuthEnabled($user);

        // 3. Verificar bloqueos de la cuenta.
        $this->statusHelper->check($user->email);

        // 4. Verificar si la contraseña está vencida.
        //    Aplica independientemente del método de login — regla de negocio global.
        $this->checkExpiredPassword($user);

        $user->userIsLogin($user->email);

        $token = $user->createToken('google-oauth')->plainTextToken;

        return Redirect::away(config('app.frontend_url').'/auth/callback?token='.$token);
    }

    /**
     * @throws ValidationException
     */
    private function checkExpiredPassword(User $user): void
    {
        if (! $user->hasPasswordExpired()) {
            return;
        }

        $this->sendLinkAction->send(new PasswordResetLinkData($user->email), PasswordResetReason::EXPIRED_PASSWORD);

        throw ValidationException::withMessages([
            'email' => AuthErrorCode::PasswordExpired->value,
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function checkGoogleAuthEnabled(User $user): void
    {
        if (! $user->isGoogleAuthEnabled($user->email)) {
            throw ValidationException::withMessages([
                'email' => AuthErrorCode::GoogleAuthDisabled->value,
            ]);
        }
    }
}
