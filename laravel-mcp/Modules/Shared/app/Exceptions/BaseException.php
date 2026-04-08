<?php

namespace Modules\Shared\Exceptions;

use BackedEnum;
use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Shared\Enums\SharedErrorCode;
use Modules\Shared\Traits\JsonResponseTrait;

abstract class BaseException extends Exception
{
    use JsonResponseTrait;

    /** @var array<string, mixed> */
    protected array $details = [];

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        private readonly BackedEnum $errorCode = SharedErrorCode::BaseError,
        array $details = [],
    ) {
        parent::__construct('', $this->code);
        $this->details = $details;
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
        return $this->error($this->errorCode->value, $this->code ?: 500, $this->details !== [] ? $this->details : null);
    }
}
