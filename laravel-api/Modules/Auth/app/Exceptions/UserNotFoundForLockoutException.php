<?php

namespace Modules\Auth\Exceptions;

use Modules\Auth\Enums\AuthErrorCode;
use Modules\Shared\Exceptions\BaseException;

class UserNotFoundForLockoutException extends BaseException
{
    /**
     * @var int
     */
    protected $code = 503;

    public function __construct(string $message = '', array $details = [])
    {
        parent::__construct(
            errorCode: AuthErrorCode::LockoutUserNotFound,
            message: $message,
            details: $details,
        );
    }
}
