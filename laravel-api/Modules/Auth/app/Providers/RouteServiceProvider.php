<?php

namespace Modules\Auth\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Auth';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapAuthRoutes();
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define las rutas de autenticación del módulo.
     *
     * Usa middleware `api` porque el sistema utiliza Sanctum API tokens (stateless).
     * Los tokens se envían en el header `Authorization: Bearer {token}` — no se
     * requiere sesión, cookies ni CSRF.
     */
    protected function mapAuthRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(module_path($this->name, '/routes/auth.php'));
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
