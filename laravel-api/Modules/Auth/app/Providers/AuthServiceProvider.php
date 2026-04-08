<?php

namespace Modules\Auth\Providers;

use Modules\Auth\Actions\OAuth\Google\GoogleOAuthAction;
use Modules\Auth\Interfaces\Auth\LockoutStateStoreInterface;
use Modules\Auth\Interfaces\OAuth\CallbackStrategyInterface;
use Modules\Auth\Interfaces\OAuth\RedirectStrategyInterface;
use Modules\Auth\Security\PasswordSecurity;
use Modules\Auth\Stores\LockoutStateStore;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AuthServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Auth';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'auth';

    public function boot(): void
    {
        PasswordSecurity::default();
    }

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Mapa de proveedores OAuth soportados.
     *
     * Para agregar un nuevo proveedor (ej. Outlook), basta con añadir
     * la clave del parámetro de ruta y la clase de acción correspondiente.
     *
     * @var array<string, class-string>
     */
    private array $oauthProviders = [
        'google' => GoogleOAuthAction::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(LockoutStateStoreInterface::class, LockoutStateStore::class);

        $this->bindOAuthStrategies();
    }

    /**
     * Registrar el binding de las estrategias OAuth según el parámetro {provider} de la ruta.
     *
     * Al resolver RedirectStrategyInterface o CallbackStrategyInterface, el contenedor
     * lee el parámetro de ruta para determinar qué implementación concreta usar.
     * GoogleOAuthAction implementa ambas interfaces, por lo que se resuelve una
     * sola instancia compartida para las dos.
     */
    private function bindOAuthStrategies(): void
    {
        $resolver = function () {
            $provider = request()->route('provider');
            $actionClass = $this->oauthProviders[$provider] ?? null;

            if (! $actionClass) {
                abort(404);
            }

            return $this->app->make($actionClass);
        };

        $this->app->bind(RedirectStrategyInterface::class, $resolver);
        $this->app->bind(CallbackStrategyInterface::class, $resolver);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
