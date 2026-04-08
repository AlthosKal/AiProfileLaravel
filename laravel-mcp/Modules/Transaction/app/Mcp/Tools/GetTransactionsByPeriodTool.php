<?php

namespace Modules\Transaction\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Actions\GetTransactionsAction;
use Modules\Transaction\Http\Data\Request\GetTransactionsByPeriodRequestData;
use Modules\Transaction\Http\Data\Response\GetTransactionsByConditionResponseData;

/**
 * *Descripción:**
 * Retorna los ingresos y egresos dentro de un rango de fechas especificado.
 *
 * *Parámetros:**
 *
 *  `date_from`: Fecha inicial del rango.
 *  `date_to`: Fecha final del rango.
 *
 * *Descripción extendida:**
 * Obtiene los ingresos y egresos comprendidos entre la fecha `date_from` y la fecha `date_to`, inclusive.
 */
#[Title('Get Transactions within for date range')]
#[Description('
**Description:**
Returns transactions within a specified date range.

**Parameters:**

* `date_from`: Start date of the range.
* `date_to`: End date of the range.

**Extended description:**
Retrieves transactions between `date_from` and `date_to`, inclusive.
')]
class GetTransactionsByPeriodTool extends Tool
{
    public function __construct(
        private readonly GetTransactionsAction $action,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, GetTransactionsByPeriodRequestData $data): ResponseFactory
    {
        /** @var GatewayUser $user */
        $user = $request->user();
        $result = $this->action->getByPeriod($data, $user);

        return Response::make(
            Response::text('Transacciones obtenidas correctamente'),
        )->withStructuredContent($result);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return GetTransactionsByPeriodRequestData::toolSchema($schema);
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return GetTransactionsByConditionResponseData::toolSchema($schema);
    }
}
