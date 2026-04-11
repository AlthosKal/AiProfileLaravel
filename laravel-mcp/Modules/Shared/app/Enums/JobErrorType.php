<?php

namespace Modules\Shared\Enums;

enum JobErrorType: string
{
    case NO_ERROR = 'no_error';
    case EXECUTION_FAILED = 'execution_failed';
    case GENERATED_FAILED = 'generated_failed';
}
