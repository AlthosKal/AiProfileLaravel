<?php

namespace Modules\Client\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;
use Modules\Client\Ai\Exceptions\PromptInjectionException;

/**
 * Middleware de dos capas que bloquea intentos de prompt injection
 * antes de que el prompt llegue al proveedor de IA.
 *
 * Capa 1 — Regex (0.1ms):
 *   Detecta patrones literales conocidos: ignorar instrucciones, escape de rol,
 *   delimitadores de contexto, extracción del system prompt, bypass de restricciones.
 *
 * Capa 2 — Heurística (0.1ms):
 *   Detecta técnicas de obfuscación: base64, homóglifos Unicode, espaciado
 *   anómalo entre letras, y densidad excesiva de símbolos de markup.
 *
 * Todos los rechazos se registran en el canal 'ai-security' para auditoría
 * y calibración de falsos positivos.
 */
class PromptInjectionMiddleware
{
    /**
     * Patrones de prompt injection (Capa 1).
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

        // Inyección de turno vía newline
        '/\n\s*(assistant|system)\s*:/i',
        '/\r\n\s*(assistant|system)\s*:/i',
    ];

    /**
     * Umbral de densidad de símbolos de markup sobre el total de caracteres.
     * Por encima de este ratio, el prompt se considera obfuscación.
     */
    private const float SYMBOL_DENSITY_THRESHOLD = 0.15;

    /**
     * Longitud mínima de secuencia base64 para considerarla sospechosa.
     * Cadenas cortas como IDs o tokens legítimos no deben disparar esto.
     */
    private const int BASE64_MIN_LENGTH = 40;

    /**
     * Mínimo de letras espaciadas consecutivas para considerar espaciado anómalo.
     * Ej: "i g n o r e" = 6 letras espaciadas.
     */
    private const int SPACED_LETTERS_MIN = 6;

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $this->runLayer1PatternMatching($prompt->prompt);
        $this->runLayer2Heuristics($prompt->prompt);

        return $next($prompt);
    }

    /**
     * Capa 1: compara el prompt contra patrones regex conocidos.
     *
     * @throws PromptInjectionException
     */
    private function runLayer1PatternMatching(string $userPrompt): void
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $userPrompt)) {
                $this->logAndThrow(
                    layer: 'pattern_matching',
                    reason: "Matched pattern: {$pattern}",
                    prompt: $userPrompt,
                );
            }
        }
    }

    /**
     * Capa 2: detecta técnicas de obfuscación que evaden regex literales.
     *
     * @throws PromptInjectionException
     */
    private function runLayer2Heuristics(string $userPrompt): void
    {
        $this->detectBase64Obfuscation($userPrompt);
        $this->detectUnicodeHomoglyphs($userPrompt);
        $this->detectSpacedLetters($userPrompt);
        $this->detectSymbolDensity($userPrompt);
    }

    /**
     * Detecta bloques base64 largos incrustados en el prompt.
     * Los IDs y tokens legítimos son cortos; payloads codificados son largos.
     *
     * @throws PromptInjectionException
     */
    private function detectBase64Obfuscation(string $userPrompt): void
    {
        $pattern = '/[A-Za-z0-9+\/]{'.self::BASE64_MIN_LENGTH.',}={0,2}/';

        if (preg_match($pattern, $userPrompt)) {
            $this->logAndThrow(
                layer: 'heuristic',
                reason: 'Suspected base64 encoded payload detected.',
                prompt: $userPrompt,
            );
        }
    }

    /**
     * Detecta caracteres Unicode fuera del rango latin extendido.
     * Los homóglifos cirílicos/griegos imitan letras latinas para evadir patrones.
     *
     * @throws PromptInjectionException
     */
    private function detectUnicodeHomoglyphs(string $userPrompt): void
    {
        // Detecta caracteres no-ASCII que no son acentos/tildes del español
        // (rango latin extendido legítimo: U+00C0–U+024F)
        if (preg_match('/[^\x00-\x{024F}]/u', $userPrompt)) {
            $this->logAndThrow(
                layer: 'heuristic',
                reason: 'Non-latin Unicode characters detected (possible homoglyph attack).',
                prompt: $userPrompt,
            );
        }
    }

    /**
     * Detecta el patrón "i g n o r e" — letras individuales separadas por espacios.
     * Esta técnica evade los patrones regex literales conservando la intención.
     *
     * @throws PromptInjectionException
     */
    private function detectSpacedLetters(string $userPrompt): void
    {
        // Busca secuencias de N letras individuales separadas por un espacio
        $min = self::SPACED_LETTERS_MIN;
        $pattern = '/(\b\w\s){'.($min - 1).'}\w\b/';

        if (preg_match($pattern, $userPrompt)) {
            $this->logAndThrow(
                layer: 'heuristic',
                reason: 'Anomalous character spacing pattern detected.',
                prompt: $userPrompt,
            );
        }
    }

    /**
     * Detecta densidad excesiva de símbolos de markup (<, >, {, }, [, ]).
     * Una proporción alta sobre el total de caracteres sugiere obfuscación
     * o inyección de estructura de contexto.
     *
     * @throws PromptInjectionException
     */
    private function detectSymbolDensity(string $userPrompt): void
    {
        $totalLength = mb_strlen($userPrompt);

        if ($totalLength === 0) {
            return;
        }

        $symbolCount = preg_match_all('/[<>{}\[\]]/', $userPrompt);
        $density = $symbolCount / $totalLength;

        if ($density > self::SYMBOL_DENSITY_THRESHOLD) {
            $this->logAndThrow(
                layer: 'heuristic',
                reason: sprintf(
                    'Symbol density %.2f%% exceeds threshold %.0f%%.',
                    $density * 100,
                    self::SYMBOL_DENSITY_THRESHOLD * 100,
                ),
                prompt: $userPrompt,
            );
        }
    }

    /**
     * Registra el rechazo en el canal de seguridad y lanza la excepción de dominio.
     *
     * El prompt se trunca en el log para evitar almacenar payloads completos.
     *
     * @throws PromptInjectionException
     */
    private function logAndThrow(string $layer, string $reason, string $prompt): never
    {
        Log::channel('stack')->warning('Prompt injection attempt blocked', [
            'layer' => $layer,
            'reason' => $reason,
            'prompt_preview' => mb_substr($prompt, 0, 120).'...',
        ]);

        throw new PromptInjectionException(
            detectionLayer: $layer,
            reason: $reason,
        );
    }
}
