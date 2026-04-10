<?php

namespace Modules\Client\Http\Ai\Data;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * DTO para la solicitud de prompt al agente de IA.
 *
 * conversation_id es nullable: si se omite, el agente inicia una nueva conversación.
 * Si se provee, el agente continúa la conversación existente.
 */
class PromptRequestData extends Data
{
    public function __construct(
        #[Rule('required|string|min:1|max:4000')]
        public string $message,
        #[Rule('nullable|uuid')]
        public ?string $conversation_id,
    ) {}
}
