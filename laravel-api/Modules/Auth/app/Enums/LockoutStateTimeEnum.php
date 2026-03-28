<?php

namespace Modules\Auth\Enums;

enum LockoutStateTimeEnum: int
{
    case TTL_24H = 86400;
}
