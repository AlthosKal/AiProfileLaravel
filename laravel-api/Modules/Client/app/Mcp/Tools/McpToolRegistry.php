<?php

namespace Modules\Client\Mcp\Tools;

use Mcp\Schema\Tool as McpTool;
use Modules\Client\Ai\Tools\ExecuteDocumentScriptTool;
use Modules\Client\Ai\Tools\GetAllTransactionsTool;
use Modules\Client\Ai\Tools\GetTransactionByTypeTool;
use Modules\Client\Ai\Tools\GetTransactionsByAmountRangeTool;
use Modules\Client\Ai\Tools\GetTransactionsByPeriodTool;
use Modules\Client\Ai\Tools\McpProxyTool;
use Modules\Client\Mcp\Client\AiAssistantMcpClient;

/**
 * Resuelve la clase PHP concreta que corresponde a cada tool del servidor MCP.
 *
 * Laravel AI usa class_basename($tool) para identificar cada tool al hacer
 * el matching con la respuesta del LLM. Por ello, cada tool MCP necesita
 * su propia subclase PHP cuyo nombre coincida exactamente con el nombre
 * de la tool en el servidor.
 *
 * Cuando se agregue una nueva tool al servidor MCP:
 *   1. Crear su subclase en Modules/Client/app/Ai/Tools/
 *   2. Registrarla en TOOL_MAP con el nombre MCP exacto como clave
 */
final class McpToolRegistry
{
    /**
     * Mapa de nombre MCP → clase PHP concreta del proxy.
     *
     * @var array<string, class-string<McpProxyTool>>
     */
    private const array TOOL_MAP = [
        'GetAllTransactionsTool' => GetAllTransactionsTool::class,
        'GetTransactionsByPeriodTool' => GetTransactionsByPeriodTool::class,
        'GetTransactionsByAmountRangeTool' => GetTransactionsByAmountRangeTool::class,
        'GetTransactionByTypeTool' => GetTransactionByTypeTool::class,
        'ExecuteDocumentScriptTool' => ExecuteDocumentScriptTool::class,
    ];

    /**
     * Resuelve la instancia de Laravel AI Tool correspondiente a la tool MCP dada.
     * Retorna null si la tool no tiene clase proxy registrada.
     */
    public static function resolve(AiAssistantMcpClient $client, McpTool $mcpTool): ?McpProxyTool
    {
        $class = self::TOOL_MAP[$mcpTool->name] ?? null;

        if ($class === null) {
            return null;
        }

        return new $class($client, $mcpTool);
    }
}
