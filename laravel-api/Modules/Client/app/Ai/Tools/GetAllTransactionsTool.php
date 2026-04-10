<?php

namespace Modules\Client\Ai\Tools;

/**
 * Proxy hacia la tool GetAllTransactionsTool del servidor MCP.
 *
 * El nombre de clase debe coincidir exactamente con el nombre de la tool
 * en el servidor para que Laravel AI pueda hacer el matching al despachar
 * la respuesta del LLM (usa class_basename()).
 */
final class GetAllTransactionsTool extends McpProxyTool {}
