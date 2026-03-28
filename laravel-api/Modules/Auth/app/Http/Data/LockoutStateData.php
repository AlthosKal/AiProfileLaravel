<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Models\UserSecurityEvent;

final readonly class LockoutStateData
{
    public function __construct(
        public bool $permanent,
        public int $count,
        public string $errorCode,
        public ?UserSecurityEvent $user_security_event = null,
        public ?bool $captcha_enabled = null,
        public ?int $duration = null,
        public ?int $retry_after = null,
    ) {}
}
