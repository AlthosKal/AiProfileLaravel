<?php

namespace Modules\Client\Ai\Tools\Tavily;

/**
 * Proxy hacia la tool tavily-search del servidor MCP de Tavily.
 *
 * Provee búsqueda web en tiempo real con filtrado avanzado y
 * capacidades de búsqueda específica por dominio.
 *
 * El nombre de clase debe coincidir exactamente con el nombre de la tool
 * en el servidor para que Laravel AI pueda hacer el matching (usa class_basename()).
 */
final class TavilySearchTool extends TavilyMcpProxyTool {}
