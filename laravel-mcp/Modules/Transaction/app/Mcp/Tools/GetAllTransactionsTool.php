<?php

namespace Modules\Transaction\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Actions\GetTransactionsAction;
use Modules\Transaction\Http\Data\Request\GetTransactionsByMcpRequestData;
use Modules\Transaction\Http\Data\Response\GetTransactionsPaginatedData;

/**
 * *Descripción:**
 * Retorna todas las transacciones del usuario autenticado de forma paginada.
 *
 * *Parámetros:**
 *
 *  `per_page`: Número de transacciones por página.
 *  `page`: Número de página a recuperar.
 *
 * *Descripción extendida:**
 * Obtiene el listado completo de transacciones del usuario paginadas según los parámetros indicados,
 * junto con metadata de paginación: total de registros, página actual y última página disponible.
 */
#[Description('
**Description:**
Returns all transactions of the authenticated user in a paginated format.

**Parameters:**

* `per_page`: Number of transactions per page. Must be at least 1.
* `page`: Page number to retrieve. Must be at least 1.

**Extended description:**
Retrieves the full list of the user\'s transactions paginated according to the given parameters,
along with pagination metadata: total records, current page, and last available page.
')]
class GetAllTransactionsTool extends Tool
{
    public function __construct(
        private readonly GetTransactionsAction $action,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, GetTransactionsByMcpRequestData $data): ResponseFactory
    {
        /** @var GatewayUser $user */
        $user = $request->user();
        $result = $this->action->getAllByMcp($data, $user);

        return Response::make(
            Response::text('Paginated Transactions successfully listed.')
        )->withStructuredContent($result);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return GetTransactionsByMcpRequestData::toolSchema($schema);
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
