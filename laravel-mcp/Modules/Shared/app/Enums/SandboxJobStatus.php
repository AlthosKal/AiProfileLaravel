<?php

namespace Modules\Shared\Enums;

enum SandboxJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
