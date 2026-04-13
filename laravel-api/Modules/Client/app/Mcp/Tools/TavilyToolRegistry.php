<?php

namespace Modules\Client\Mcp\Tools;

use Mcp\Schema\Tool as McpTool;
use Modules\Client\Ai\Tools\Tavily\TavilyCrawlTool;
use Modules\Client\Ai\Tools\Tavily\TavilyExtractTool;
use Modules\Client\Ai\Tools\Tavily\TavilyMapTool;
use Modules\Client\Ai\Tools\Tavily\TavilyMcpProxyTool;
use Modules\Client\Ai\Tools\Tavily\TavilySearchTool;
use Modules\Client\Mcp\Client\TavilyMcpClient;

/**
 * Resuelve la clase PHP concreta que corresponde a cada tool del servidor MCP de Tavily.
 *
 * Sigue el mismo patrón que McpToolRegistry pero orientado al cliente de Tavily.
 * Los nombres de las claves deben coincidir exactamente con los nombres que
 * expone el servidor Tavily MCP.
 *
 * Cuando Tavily agregue nuevas tools:
 *   1. Crear su subclase en Modules/Client/app/Ai/Tools/Tavily/
 *   2. Registrarla aquí con el nombre MCP exacto como clave
 */
final class TavilyToolRegistry
{
    /**
     * Mapa de nombre MCP → clase PHP concreta del proxy.
     *
     * @var array<string, class-string<TavilyMcpProxyTool>>
     */
    private const array TOOL_MAP = [
        'tavily-search' => TavilySearchTool::class,
        'tavily-extract' => TavilyExtractTool::class,
        'tavily-map' => TavilyMapTool::class,
        'tavily-crawl' => TavilyCrawlTool::class,
    ];

    /**
     * Resuelve la instancia de Laravel AI Tool correspondiente a la tool Tavily MCP dada.
     * Retorna null si la tool no tiene clase proxy registrada.
     */
    public static function resolve(TavilyMcpClient $client, McpTool $mcpTool): ?TavilyMcpProxyTool
    {
        $class = self::TOOL_MAP[$mcpTool->name] ?? null;

        if ($class === null) {
            return null;
        }

        return new $class($client, $mcpTool);
    }
}
