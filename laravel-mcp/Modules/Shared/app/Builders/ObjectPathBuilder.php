<?php

namespace Modules\Shared\Builders;

interface ObjectPathBuilder
{
    public static function build(string $filename): string;
}
