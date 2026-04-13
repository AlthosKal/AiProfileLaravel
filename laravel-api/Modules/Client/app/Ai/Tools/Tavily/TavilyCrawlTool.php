<?php

namespace Modules\Client\Ai\Tools\Tavily;

/**
 * Proxy hacia la tool tavily-crawl del servidor MCP de Tavily.
 *
 * Rastreador web que explora sistemáticamente sitios web,
 * siguiendo enlaces para recopilar información en profundidad.
 *
 * El nombre de clase debe coincidir exactamente con el nombre de la tool
 * en el servidor para que Laravel AI pueda hacer el matching (usa class_basename()).
 */
final class TavilyCrawlTool extends TavilyMcpProxyTool {}
