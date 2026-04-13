<?php

namespace Modules\Client\Ai\Exceptions;

use Illuminate\Http\JsonResponse;
use Modules\Shared\Enums\SharedErrorCode;
use Modules\Shared\Exceptions\BaseException;

class PromptInjectionException extends BaseException
{
    public function __construct(
        public readonly string $detectionLayer,
        public readonly string $reason,
    ) {
        parent::__construct(
            errorCode: SharedErrorCode::PromptInjectionDetected,
            details: [
                'layer' => $detectionLayer,
                'reason' => $reason,
            ],
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => SharedErrorCode::PromptInjectionDetected->value,
            'message' => 'El mensaje contiene contenido no permitido.',
        ], 422);
    }
}
