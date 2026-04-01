<?php

namespace Modules\Shared\Enums;

enum MiddlewaresFramework: string
{
    case GUEST = 'guest';
    case AUTH = 'auth:';
    case SIGNED = 'signed';

    public static function with(MiddlewaresFramework $framework, string $value): string
    {
        return "$framework->value$value";
    }
}
