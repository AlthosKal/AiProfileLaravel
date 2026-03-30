<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Función con la que se crea el link para cambiar contraseña
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Configurar reglas por defecto para contraseñas seguras
        Password::defaults(function () {
            return Password::min(8)
                ->letters()      // Al menos una letra
                ->mixedCase()    // Mayúsculas y minúsculas
                ->numbers()      // Al menos un número
                ->symbols();     // Al menos un símbolo
        });
    }
}
