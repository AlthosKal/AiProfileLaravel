<?php

namespace Modules\Transaction\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Modules\Transaction\Mcp\Tools\GetAllTransactionsTool;
use Modules\Transaction\Mcp\Tools\GetTransactionByTypeTool;
use Modules\Transaction\Mcp\Tools\GetTransactionsByAmountRangeTool;
use Modules\Transaction\Mcp\Tools\GetTransactionsByPeriodTool;

/**
 * Este servidor proporciona capacidades de consulta y análisis de transacciones financieras.
 * Las herramientas disponibles permitirán consultar, filtrar y resumir transacciones.
 * Se agregarán más descripciones de herramientas a medida que sean implementadas.
 */
#[Name('Ai Financial Assistant')]
#[Version('0.0.1')]
#[Instructions('This server provides financial transaction data and analysis capabilities. Tools will be available to query, filter, and summarize transactions. More tool descriptions will be added as they are implemented.')]
class AiAssistantServer extends Server
{
    protected array $tools = [
        GetTransactionsByPeriodTool::class,
        GetTransactionsByAmountRangeTool::class,
        GetAllTransactionsTool::class,
        GetTransactionByTypeTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
