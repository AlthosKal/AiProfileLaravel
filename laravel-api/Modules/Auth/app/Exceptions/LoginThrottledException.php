<?php

namespace Modules\Auth\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Http\Data\LockoutStateData;
use Modules\Shared\Exceptions\BaseException;

class LoginThrottledException extends BaseException
{
    public function __construct(
        private readonly LockoutStateData $lockoutState,
    ) {
        parent::__construct(
            errorCode: AuthErrorCode::LoginThrottled,
        );
    }

    public function render(): JsonResponse
    {
        return $this->error(
            status: AuthErrorCode::LoginThrottled->value,
            httpStatus: Response::HTTP_TOO_MANY_REQUESTS,
            data: [
                'errors' => [
                    'email' => [AuthErrorCode::LoginThrottled->value],
                ],
                'lockout' => [
                    'captcha_required' => $this->lockoutState->captcha_enabled ?? false,
                    'retry_after' => $this->lockoutState->retry_after,
                    'permanently_blocked' => $this->lockoutState->permanent,
                ],
            ],
        );
    }
}
