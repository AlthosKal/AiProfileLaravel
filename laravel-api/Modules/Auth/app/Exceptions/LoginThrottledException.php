<?php

namespace Modules\Auth\Exceptions;

use Illuminate\Http\JsonResponse;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Http\Data\LockoutStateData;
use Modules\Shared\Exceptions\BaseException;

class LoginThrottledException extends BaseException
{
    /**
     * @var int
     */
    protected $code = 429;

    public function __construct(
        private readonly LockoutStateData $lockoutState,
        string $message = '',
    ) {
        parent::__construct(
            errorCode: AuthErrorCode::LoginThrottled,
            message: $message,
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => AuthErrorCode::LoginThrottled->value,
            'errors' => [
                'email' => [AuthErrorCode::LoginThrottled->value],
            ],
            'lockout' => [
                'captcha_required' => $this->lockoutState->captcha_enabled ?? false,
                'retry_after' => $this->lockoutState->retry_after,
                'permanently_blocked' => $this->lockoutState->permanent,
            ],
        ], 429);
    }
}
