<?php

namespace Modules\Auth\Interfaces\OAuth;

use Symfony\Component\HttpFoundation\RedirectResponse;

interface CallbackStrategyInterface
{
    public function callback(): RedirectResponse;
}
