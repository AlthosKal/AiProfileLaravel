<?php

namespace Modules\Client\Interface;

use Mcp\Client;

interface McpClientInterface
{
    public function connect(string $endpoint, array $headers) : void;
    public function disconnect(Client $client) : void;
}
