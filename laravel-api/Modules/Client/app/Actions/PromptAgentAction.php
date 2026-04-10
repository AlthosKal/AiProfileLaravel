<?php

namespace Modules\Client\Actions;

use Laravel\Ai\Responses\StreamableAgentResponse;
use Modules\Auth\Models\User;
use Modules\Client\Ai\Agents\AiFinancialAssistant;
use Modules\Client\Http\Ai\Data\PromptRequestData;
use Modules\Client\Mcp\Client\AiAssistantMcpClient;

/**
 * Orquesta el ciclo completo de un prompt al agente financiero.
 *
 * Flujo:
 *   1. Conecta el cliente MCP al servidor laravel-mcp con JWT del usuario
 *   2. Instancia el agente inyectando el cliente MCP conectado
 *   3. Decide si continuar conversación existente o iniciar una nueva
 *   4. Retorna un StreamableAgentResponse para SSE
 */
readonly class PromptAgentAction
{
    public function __construct(
        private AiAssistantMcpClient $mcpClient,
    ) {}

    public function execute(PromptRequestData $data, User $user): StreamableAgentResponse
    {
        $this->mcpClient->connectForUser($user->email);

        $agent = new AiFinancialAssistant($this->mcpClient);

        if ($data->conversation_id !== null) {
            $agent->continue($data->conversation_id, as: $user);
        } else {
            $agent->forUser($user);
        }

        return $agent->stream($data->message);
    }
}
