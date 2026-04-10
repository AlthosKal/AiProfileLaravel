<?php

namespace Modules\Client\Interface;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ListToolsResult;

interface McpClientInterface
{
    public function connect(string $endpoint, array $headers): void;

    public function listTools(): ListToolsResult;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callTool(string $name, array $arguments = []): CallToolResult;

    public function disconnect(): void;
}
