<?php

namespace Modules\Transaction\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class TransactionServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Transaction';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'transaction';

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
