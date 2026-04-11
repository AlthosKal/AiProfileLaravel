<?php

namespace Modules\Transaction\Enums;

enum FileType: string
{
    case IMPORT = 'import';
    case EXPORT = 'export';
    case GENERATED = 'generated';
}
