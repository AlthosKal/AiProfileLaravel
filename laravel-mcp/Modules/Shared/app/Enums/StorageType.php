<?php

namespace Modules\Shared\Enums;

enum StorageType: string
{
    case DISK = 's3';
    case LOCAL = 'local';
}
