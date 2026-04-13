<?php

namespace Modules\Client\Mcp\Client;

use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Exception\ConnectionException;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ListToolsResult;
use Modules\Client\Interface\McpClientInterface;

/**
 * Cliente MCP que se conecta al servidor remoto de Tavily via HTTP transport.
 *
 * Tavily expone un servidor MCP público que provee herramientas de búsqueda
 * y extracción web en tiempo real. La autenticación se realiza enviando
 * la API key como query parameter en la URL del endpoint.
 *
 * Flujo:
 *   1. connect() — establece sesión MCP con la URL que incluye la API key
 *   2. listTools() / callTool() — operaciones sobre el servidor Tavily
 *   3. disconnect() — cierra la sesión
 *
 * @see https://docs.tavily.com/documentation/mcp
 */
class TavilyMcpClient implements McpClientInterface
{
    private ?Client $client = null;

    /**
     * Establece la conexión al servidor MCP remoto de Tavily.
     * La API key se pasa como query parameter en la URL.
     *
     * @param  array<string, string>  $headers
     *
     * @throws ConnectionException
     */
    public function connect(string $endpoint, array $headers = []): void
    {
        $this->client = $this->buildClient();
        $transport = $this->buildTransport($endpoint, $headers);
        $this->client->connect($transport);
    }

    /**
     * Conecta al servidor remoto de Tavily usando la API key configurada.
     *
     * @throws ConnectionException
     */
    public function connectToTavily(): void
    {
        $apiKey = config('ai.tavily.api_key');
        $baseUrl = rtrim(config('ai.tavily.url', 'https://mcp.tavily.com/mcp/'), '/');
        $endpoint = $baseUrl.'/?tavilyApiKey='.$apiKey;

        $this->connect($endpoint);
    }

    /**
     * Lista las tools disponibles en el servidor Tavily MCP.
     *
     * @throws ConnectionException si no hay conexión activa
     */
    public function listTools(): ListToolsResult
    {
        return $this->resolveClient()->listTools();
    }

    /**
     * Invoca una tool en el servidor Tavily MCP.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws ConnectionException si no hay conexión activa
     */
    public function callTool(string $name, array $arguments = []): CallToolResult
    {
        return $this->resolveClient()->callTool($name, $arguments);
    }

    public function disconnect(): void
    {
        $this->client?->disconnect();
        $this->client = null;
    }

    public function isConnected(): bool
    {
        return $this->client?->isConnected() ?? false;
    }

    /**
     * @throws ConnectionException si no se ha establecido conexión
     */
    private function resolveClient(): Client
    {
        if (! $this->client?->isConnected()) {
            throw new ConnectionException('Tavily MCP client is not connected. Call connectToTavily() first.');
        }

        return $this->client;
    }

    private function buildClient(): Client
    {
        return Client::builder()
            ->setClientInfo(
                name: config('ai.mcp-client.name', 'ai-profile-client'),
                version: config('ai.mcp-client.version', '1.0.0'),
                description: config('ai.mcp-client.description'),
            )
            ->setInitTimeout(config('ai.mcp-client.init-timeout', 30))
            ->setRequestTimeout(config('ai.mcp-client.request-timeout', 120))
            ->setMaxRetries(config('ai.mcp-client.max-retries', 3))
            ->build();
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function buildTransport(string $endpoint, array $headers): HttpTransport
    {
        return new HttpTransport(
            endpoint: $endpoint,
            headers: $headers,
        );
    }
}
