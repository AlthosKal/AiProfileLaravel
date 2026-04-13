<?php

namespace Modules\Client\Mcp\Client;

use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Exception\ConnectionException;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ListToolsResult;
use Modules\Client\Interface\McpClientInterface;
use Modules\Shared\Security\InternalJwtSecurity;

/**
 * Cliente MCP que se conecta al servidor laravel-mcp via HTTP transport.
 *
 * El cliente mantiene la conexión activa durante su ciclo de vida
 * (scoped al request via service provider). Autentica cada conexión
 * con un JWT RS256 interno generado por InternalJwtSecurity.
 *
 * Flujo:
 *   1. connect($endpoint, $email) — establece sesión MCP con JWT interno
 *   2. listTools() / callTool() — operaciones sobre el servidor conectado
 *   3. disconnect() — cierra la sesión (llamado automáticamente si se usa scoped)
 */
class AiAssistantMcpClient implements McpClientInterface
{
    private ?Client $client = null;

    public function __construct(
        private readonly InternalJwtSecurity $jwtSecurity,
    ) {}

    /**
     * Establece la conexión MCP al endpoint dado, autenticando con JWT interno
     * generado para el email del usuario autenticado.
     *
     * @throws ConnectionException
     */
    /**
     * @param  array<string, string>  $headers
     */
    public function connect(string $endpoint, array $headers): void
    {
        $this->client = $this->buildClient();
        $transport = $this->buildTransport($endpoint, $headers);
        $this->client->connect($transport);
    }

    /**
     * Conecta al servidor MCP del laravel-mcp usando el email del usuario
     * para generar el JWT interno de autenticación.
     *
     * @throws ConnectionException
     */
    public function connectForUser(string $email): void
    {
        $endpoint = rtrim(config('services.laravel-mcp.url'), '/').'/mcp/ai-assistant';
        $jwt = $this->jwtSecurity->forEmail($email);

        $this->connect($endpoint, [
            'Authorization' => 'Bearer '.$jwt,
        ]);
    }

    /**
     * Lista las tools disponibles en el servidor MCP conectado.
     *
     * @throws ConnectionException si no hay conexión activa
     */
    public function listTools(): ListToolsResult
    {
        return $this->resolveClient()->listTools();
    }

    /**
     * Invoca una tool en el servidor MCP conectado.
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
            throw new ConnectionException('MCP client is not connected. Call connect() or connectForUser() first.');
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
