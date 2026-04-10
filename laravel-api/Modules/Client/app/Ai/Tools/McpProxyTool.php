<?php

namespace Modules\Client\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mcp\Schema\Tool as McpTool;
use Modules\Client\Mcp\Client\AiAssistantMcpClient;
use Stringable;

/**
 * Tool de Laravel AI que actúa como proxy hacia una tool del servidor MCP.
 *
 * El nombre de la tool enviado al LLM y usado para el matching de respuesta
 * proviene de class_basename() (convención de Laravel AI). Por ello, esta
 * clase base no se usa directamente — cada tool MCP del servidor tiene su
 * propia subclase PHP con el nombre que coincide con el nombre MCP.
 *
 * El inputSchema del servidor MCP viene como array JSON Schema crudo. Se
 * convierte a tipos de Laravel AI para que el gateway lo serialice correctamente.
 *
 * @see McpToolRegistry para la creación de las subclases concretas
 */
abstract class McpProxyTool implements Tool
{
    public function __construct(
        protected readonly AiAssistantMcpClient $mcpClient,
        protected readonly McpTool $mcpTool,
    ) {}

    public function description(): Stringable|string
    {
        return $this->mcpTool->description ?? '';
    }

    /**
     * Ejecuta la tool invocando al servidor MCP con los argumentos que el LLM proporcionó.
     */
    public function handle(Request $request): Stringable|string
    {
        $result = $this->mcpClient->callTool(
            name: $this->mcpTool->name,
            arguments: $request->all(),
        );

        // Extraer el contenido de texto del resultado MCP
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
     * El servidor MCP devuelve un array con la estructura:
     *   { type: "object", properties: { param: { type: "string", description: "..." } }, required: [...] }
     *
     * Lo pasamos tal cual como un array de tipos raw, aprovechando que Laravel AI
     * internamente serializa el schema a JSON para el proveedor.
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

            if ($type !== null) {
                if (in_array($name, $required, true)) {
                    $type = $type->required();
                }

                $result[$name] = $type;
            }
        }

        return $result;
    }

    /**
     * Convierte una definición de propiedad JSON Schema al tipo correspondiente de Laravel AI.
     */
    private function mapPropertyToType(JsonSchemaTypeFactory $factory, array $definition): ?Type
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
