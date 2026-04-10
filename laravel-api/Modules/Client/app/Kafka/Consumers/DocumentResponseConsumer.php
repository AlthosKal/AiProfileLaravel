<?php

namespace Modules\Client\Kafka\Consumers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\Handler;
use Junges\Kafka\Contracts\MessageConsumer;

/**
 * Consume respuestas de generación de documentos desde laravel-mcp.
 *
 * Escucha el topic ai-profile.business.responses. Al recibir un mensaje,
 * almacena el resultado en Redis keyed por correlation_id con TTL de 10 minutos.
 *
 * Estructura esperada del payload:
 * {
 *   "correlation_id": "uuid-v4",
 *   "download_url": "https://...",
 *   "filename": "reporte.pdf",
 *   "expires_in_minutes": 10
 * }
 *
 * El frontend puede hacer polling a un endpoint de laravel-api que consulte
 * Redis por correlation_id para obtener el resultado cuando esté disponible.
 */
final class DocumentResponseConsumer implements Handler
{
    private const string CACHE_PREFIX = 'document_response:';

    private const int CACHE_TTL_SECONDS = 600;

    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $message->getBody();

        if (! is_array($body)) {
            Log::warning('DocumentResponseConsumer: payload inválido recibido', [
                'body' => $body,
            ]);

            return;
        }

        $correlationId = $body['correlation_id'] ?? null;

        if (blank($correlationId)) {
            Log::warning('DocumentResponseConsumer: mensaje sin correlation_id', ['body' => $body]);

            return;
        }

        $payload = [
            'download_url' => $body['download_url'] ?? null,
            'filename' => $body['filename'] ?? null,
            'expires_in_minutes' => $body['expires_in_minutes'] ?? 10,
            'received_at' => now()->toIso8601String(),
        ];

        Cache::store('redis')->put(
            self::CACHE_PREFIX.$correlationId,
            $payload,
            self::CACHE_TTL_SECONDS,
        );

        Log::info('DocumentResponseConsumer: respuesta almacenada en Redis', [
            'correlation_id' => $correlationId,
            'filename' => $payload['filename'],
        ]);
    }
}
