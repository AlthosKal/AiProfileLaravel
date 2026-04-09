<?php
namespace Modules\Client\App\Mcp\Client;

use Mcp\Client\Transport\HttpTransport;
use Mcp\Exception\ConnectionException;
use Modules\Client\Interface\McpClientInterface;
use Mcp\Client;

class AiAssistantClient implements McpClientInterface
{

    /**
     * @throws ConnectionException
     */
    public function connect(string $endpoint, array $headers): void
    {
        $client = $this->buildConnection();
        $transport = $this->buildTransport($endpoint, $headers);
        $client->connect($transport);

    }

    public function disconnect(Client $client) : void
    {
        $client->disconnect();
    }

    private function buildConnection() : Client
    {
        return Client::builder()
            ->setClientInfo(
                name: config('ai.mcp-client.name'),
                version: config('ai.mcp-client.version'),
                description: config('ai.mcp-client.description'),
            )
            ->setInitTimeout(config('ai.mcp-client.init-timeout'))
            ->setRequestTimeout(config('ai.mcp-client.request-timeout'))
            ->setMaxRetries(config('ai.mcp-client.max-retries'))
            ->build();
    }

    private function buildTransport(string $endpoint, array $headers) : HttpTransport
    {
        return new HttpTransport(
            endpoint: $endpoint,
            headers: $headers,
        );
    }
}
