<?php

namespace App\Actions\Module\Concerns;

trait HasConsoleOutput
{
    protected function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "\033[0;36m$message\033[0m\n";
        }
    }

    protected function success(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "\033[0;32m$message\033[0m\n";
        }
    }

    protected function warn(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "\033[0;33m$message\033[0m\n";
        }
    }

    protected function error(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "\033[0;31m$message\033[0m\n";
        }
    }
}
