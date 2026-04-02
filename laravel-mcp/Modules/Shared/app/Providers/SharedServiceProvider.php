<?php

namespace Modules\Shared\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class SharedServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Shared';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'shared';

    // /**
    //  * Command classes to register.
    //  *
    //  * @var string[]
    //  */
    // protected array $commands = [];

    public function boot(): void {}

    /**
     * Provider classes to register.
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

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
