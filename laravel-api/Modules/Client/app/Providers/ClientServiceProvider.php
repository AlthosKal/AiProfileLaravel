<?php

namespace Modules\Client\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ClientServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Client';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'client';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
