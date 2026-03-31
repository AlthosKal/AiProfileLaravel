<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthErrorCode;

final readonly class LockoutStateData
{
    public function __construct(
        public bool $permanent,
        public int $count,
        public AuthErrorCode $errorCode,
        public ?bool $captcha_enabled = null,
        public ?int $duration = null,
        public ?int $retry_after = null,
    ) {}
}
