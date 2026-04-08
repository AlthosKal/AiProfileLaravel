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
use Modules\Transaction\Http\Data\Request\GetTransactionsByTypeRequestData;
use Modules\Transaction\Http\Data\Response\GetTransactionsPaginatedData;

/**
 * *Descripción:**
 * Retorna las transacciones del usuario autenticado filtradas por tipo, de forma paginada.
 *
 * *Parámetros:**
 *
 *  `type`: Tipo de transacción a filtrar. Valores aceptados: "income" o "expense".
 *  `per_page`: Número de transacciones por página.
 *  `page`: Número de página a recuperar.
 *
 * *Descripción extendida:**
 * Obtiene el listado de transacciones del usuario filtradas por el tipo indicado y paginadas
 * según los parámetros dados, junto con metadata de paginación: total de registros, página actual
 * y última página disponible.
 */
#[Title('Get Transactions by Type')]
#[Description('
**Description:**
Returns the authenticated user\'s transactions filtered by type in a paginated format.

**Parameters:**

* `type`: Transaction type to filter by. Accepted values: "income" or "expense".
* `per_page`: Number of transactions per page. Must be at least 1.
* `page`: Page number to retrieve. Must be at least 1.

**Extended description:**
Retrieves the user\'s transactions filtered by the given type and paginated according to the given
parameters, along with pagination metadata: total records, current page, and last available page.
')]
class GetTransactionByTypeTool extends Tool
{
    public function __construct(
        private readonly GetTransactionsAction $action,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, GetTransactionsByTypeRequestData $data): ResponseFactory
    {
        /** @var GatewayUser $user */
        $user = $request->user();
        $result = $this->action->getByType($data, $user);

        return Response::make(
            Response::text('Transactions filtered by type successfully listed.')
        )->withStructuredContent($result);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return GetTransactionsByTypeRequestData::toolSchema($schema);
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return GetTransactionsPaginatedData::toolSchema($schema);
    }
}
