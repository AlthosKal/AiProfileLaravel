<?php

namespace Modules\Shared\Exceptions;

use BackedEnum;
use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Shared\Enums\SharedErrorCode;

abstract class BaseException extends Exception
{
    /** @var array<string, mixed> */
    protected array $details = [];

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        private readonly BackedEnum $errorCode = SharedErrorCode::BaseError,
        string $message = '',
        array $details = [],
    ) {
        parent::__construct($message, $this->code);
        $this->details = $details;
    }

    /**
     * Establecer detalles adicionales de la excepción.
     *
     * @param  array<string, mixed>  $details
     */
    public function setDetails(array $details): self
    {
        $this->details = $details;

        return $this;
    }

    /**
     * Obtener el código de error.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode->value;
    }

    /**
     * Obtener los detalles de la excepción.
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Renderizar la excepción como respuesta JSON.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->errorCode->value,
            'message' => $this->getMessage(),
            'details' => $this->details,
        ], $this->code ?: 500);
    }
}
