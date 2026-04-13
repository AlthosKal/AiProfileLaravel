<?php

namespace Modules\Client\Ai\Tools\Tavily;

/**
 * Proxy hacia la tool tavily-map del servidor MCP de Tavily.
 *
 * Crea un mapa estructurado de un sitio web, útil para entender
 * la arquitectura y contenido de un dominio.
 *
 * El nombre de clase debe coincidir exactamente con el nombre de la tool
 * en el servidor para que Laravel AI pueda hacer el matching (usa class_basename()).
 */
final class TavilyMapTool extends TavilyMcpProxyTool {}
