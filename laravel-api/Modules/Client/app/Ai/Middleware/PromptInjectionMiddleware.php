<?php

namespace Modules\Client\Ai\Middleware;

use Closure;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Middleware que bloquea intentos de prompt injection antes de que el prompt
 * llegue al proveedor de IA.
 *
 * Detecta los patrones más comunes:
 *   - Instrucciones de ignorar el system prompt ("ignore previous instructions", etc.)
 *   - Intentos de escape de rol ("you are now", "act as", "pretend you are", etc.)
 *   - Inyección de delimitadores de contexto ("### system", "<system>", etc.)
 *   - Solicitudes de extracción del system prompt
 */
class PromptInjectionMiddleware
{
    /**
     * Patrones de prompt injection detectados vía regex (case-insensitive).
     *
     * @var string[]
     */
    private const array INJECTION_PATTERNS = [
        // Ignorar instrucciones previas
        '/ignore\s+(previous|prior|all|above|earlier)\s+(instructions?|prompts?|rules?|context)/i',
        '/disregard\s+(previous|prior|all|above)\s+(instructions?|prompts?|rules?)/i',
        '/forget\s+(your\s+)?(instructions?|rules?|previous|all|above)/i',

        // Escape de rol / DAN-style
        '/you\s+are\s+now\s+(?!a\s+financial)/i',
        '/act\s+as\s+(?!a\s+(financial|assistant))/i',
        '/pretend\s+(you\s+are|to\s+be)/i',
        '/jailbreak/i',
        '/\bDAN\b/',
        '/do\s+anything\s+now/i',

        // Inyección de contexto / delimitadores
        '/<\s*system\s*>/i',
        '/###\s*system/i',
        '/\[system\]/i',
        '/\[INST\]/i',
        '/<\|im_start\|>/i',

        // Extracción del prompt / instrucciones
        '/repeat\s+(your\s+)?(system\s+prompt|instructions?|initial\s+prompt)/i',
        '/what\s+(are|were)\s+your\s+(instructions?|rules?|initial\s+prompt|system\s+prompt)/i',
        '/reveal\s+(your\s+)?(system\s+prompt|instructions?|true\s+identity)/i',
        '/print\s+(your\s+)?(system\s+prompt|instructions?)/i',

        // Bypass de restricciones
        '/bypass\s+(your\s+)?(filter|restriction|rule|limitation)/i',
        '/override\s+(your\s+)?(instructions?|system|rules?)/i',
    ];

    /**
     * Evalúa el prompt entrante y bloquea si detecta un patrón de inyección.
     *
     * @throws ValidationException si el prompt contiene un intento de inyección
     */
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $this->assertSafe($prompt->prompt);

        return $next($prompt);
    }

    /**
     * @throws ValidationException
     */
    private function assertSafe(string $userPrompt): void
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $userPrompt)) {
                throw ValidationException::withMessages([
                    'message' => 'El mensaje contiene contenido no permitido.',
                ]);
            }
        }
    }
}
