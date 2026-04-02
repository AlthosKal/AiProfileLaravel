<?php

namespace Modules\Shared\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait JsonResponseTrait
{
    protected function success(string $status, mixed $data = null, int $httpStatus = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $status,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
            'path' => request()->path(),
        ], $httpStatus);
    }

    protected function error(string $status, int $httpStatus = Response::HTTP_BAD_REQUEST, mixed $data = null): JsonResponse
    {
        $body = [
            'success' => false,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'path' => request()->path(),
        ];

        if ($data !== null) {
            $body['data'] = $data;
        }

        return response()->json($body, $httpStatus);
    }
}
