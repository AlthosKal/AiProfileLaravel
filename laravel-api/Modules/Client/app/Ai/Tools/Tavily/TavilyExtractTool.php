<?php

namespace Modules\Client\Ai\Tools\Tavily;

/**
 * Proxy hacia la tool tavily-extract del servidor MCP de Tavily.
 *
 * Provee extracción inteligente de contenido de páginas web.
 *
 * El nombre de clase debe coincidir exactamente con el nombre de la tool
 * en el servidor para que Laravel AI pueda hacer el matching (usa class_basename()).
 */
final class TavilyExtractTool extends TavilyMcpProxyTool {}
