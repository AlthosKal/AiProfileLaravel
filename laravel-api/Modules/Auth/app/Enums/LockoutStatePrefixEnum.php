<?php

namespace Modules\Auth\Enums;

enum LockoutStatePrefixEnum: string
{
    case PREFIX_LOCKOUT_COUNT = 'lockout:count:';
    case PREFIX_CAPTCHA = 'lockout:captcha:';
}
