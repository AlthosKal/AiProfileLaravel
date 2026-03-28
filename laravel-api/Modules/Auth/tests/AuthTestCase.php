<?php

namespace Modules\Auth\Tests;

use Modules\Auth\Actions\LoginAction;
use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    public LoginAction $action;
}
