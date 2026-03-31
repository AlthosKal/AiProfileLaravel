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

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            errorCode: AuthErrorCode::LockoutUserNotFound,
            details: $details,
        );
    }
}
