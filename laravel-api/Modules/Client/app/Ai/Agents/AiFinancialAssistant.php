<?php

namespace Modules\Client\Ai\Agents;

use Illuminate\Support\Facades\File;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Modules\Client\Ai\Middleware\PromptInjectionMiddleware;
use Modules\Client\Mcp\Client\AiAssistantMcpClient;
use Modules\Client\Mcp\Client\TavilyMcpClient;
use Modules\Client\Mcp\Tools\McpToolRegistry;
use Modules\Client\Mcp\Tools\TavilyToolRegistry;
use Stringable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
#[MaxSteps(10)]
class AiFinancialAssistant implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        private readonly AiAssistantMcpClient $mcpClient,
        private readonly TavilyMcpClient $tavilyMcpClient,
    ) {}

    /**
     * Lee el system prompt desde el archivo Markdown del módulo.
     */
    public function instructions(): Stringable|string
    {
        return File::get(module_path('Client', 'resources/prompts/servers/AiAssistantServerPrompt.md'));
    }

    /**
     * Retorna las tools disponibles descubiertas dinámicamente desde el servidor MCP.
     * Cada tool del servidor se envuelve en un McpProxyTool que delega la ejecución
     * al cliente MCP cuando el LLM decide invocarla.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        $tools = [];

        if ($this->mcpClient->isConnected()) {
            $internalTools = collect($this->mcpClient->listTools()->tools)
                ->map(fn ($mcpTool) => McpToolRegistry::resolve($this->mcpClient, $mcpTool))
                ->filter()
                ->values()
                ->all();

            $tools = array_merge($tools, $internalTools);
        }

        if ($this->tavilyMcpClient->isConnected()) {
            $tavilyTools = collect($this->tavilyMcpClient->listTools()->tools)
                ->map(fn ($mcpTool) => TavilyToolRegistry::resolve($this->tavilyMcpClient, $mcpTool))
                ->filter()
                ->values()
                ->all();

            $tools = array_merge($tools, $tavilyTools);
        }

        return $tools;
    }

    /**
     * @return class-string[]
     */
    public function middleware(): array
    {
        return [
            PromptInjectionMiddleware::class,
        ];
    }
}
