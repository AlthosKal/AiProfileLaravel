<?php

namespace App\Providers;

use App\Auth\JwtGatewayGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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
        Auth::extend('jwt-gateway', function (Application $app) {
            return new JwtGatewayGuard($app['request']);
        });
    }
}
