<?php

namespace Modules\Auth\Enums;

enum SecurityEventTypeEnum: string
{
    case AUTO_UNBLOCK = 'auto_unblock';
    case LOCKOUT_INCREMENT = 'lockout_increment';
}
