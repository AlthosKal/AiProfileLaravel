<?php

namespace Modules\Auth\Interfaces\OAuth;

use Symfony\Component\HttpFoundation\RedirectResponse;

interface RedirectStrategyInterface
{
    public function redirect(): RedirectResponse;
}
