<?php

namespace Modules\Client\Ai\Tools\Tavily;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mcp\Schema\Tool as McpTool;
use Modules\Client\Mcp\Client\TavilyMcpClient;
use Stringable;

/**
 * Tool de Laravel AI que actúa como proxy hacia una tool del servidor MCP de Tavily.
 *
 * Sigue el mismo patrón que McpProxyTool pero tipado sobre TavilyMcpClient,
 * ya que Tavily usa su propio cliente con autenticación por API key.
 *
 * @see TavilyToolRegistry para el registro de subclases concretas
 */
abstract class TavilyMcpProxyTool implements Tool
{
    public function __construct(
        protected readonly TavilyMcpClient $tavilyMcpClient,
        protected readonly McpTool $mcpTool,
    ) {}

    public function description(): Stringable|string
    {
        return $this->mcpTool->description ?? '';
    }

    /**
     * Ejecuta la tool invocando al servidor Tavily MCP con los argumentos del LLM.
     */
    public function handle(Request $request): Stringable|string
    {
        $result = $this->tavilyMcpClient->callTool(
            name: $this->mcpTool->name,
            arguments: $request->all(),
        );

        $textParts = array_filter(
            $result->content,
            fn ($part) => ($part['type'] ?? '') === 'text',
        );

        if (empty($textParts)) {
            return json_encode($result->content) ?: '';
        }

        return implode("\n", array_column($textParts, 'text'));
    }

    /**
     * Convierte el inputSchema JSON Schema del servidor MCP a tipos de Laravel AI.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = $this->mcpTool->inputSchema['properties'] ?? [];
        $required = $this->mcpTool->inputSchema['required'] ?? [];

        if (empty($properties)) {
            return [];
        }

        $factory = new JsonSchemaTypeFactory;
        $result = [];

        foreach ($properties as $name => $definition) {
            $type = $this->mapPropertyToType($factory, $definition);

            if (in_array($name, $required, true)) {
                $type = $type->required();
            }

            $result[$name] = $type;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function mapPropertyToType(JsonSchemaTypeFactory $factory, array $definition): Type
    {
        $type = match ($definition['type'] ?? 'string') {
            'integer', 'int' => $factory->integer(),
            'number', 'float' => $factory->number(),
            'boolean', 'bool' => $factory->boolean(),
            'array' => $factory->array(),
            default => $factory->string(),
        };

        if (isset($definition['description'])) {
            $type = $type->description($definition['description']);
        }

        return $type;
    }
}
