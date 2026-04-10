<?php

namespace Modules\Client\Ai\Tools;

/**
 * Proxy hacia la tool ExecuteDocumentScriptTool del servidor MCP.
 *
 * Esta tool ejecuta scripts Python en el sandbox de laravel-mcp.
 * Las operaciones de larga duración (> 5s) se deberían enrutar
 * via Kafka en lugar de esperar la respuesta HTTP síncrona.
 */
final class ExecuteDocumentScriptTool extends McpProxyTool {}
