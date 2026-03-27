<?php

namespace Modules\Shared\Enums;

enum CircuitBreakerStatus: string
{
    case STATE_CLOSED = 'closed';
    case STATE_OPEN = 'open';
    case STATE_HALF_OPEN = 'half_open';
}
