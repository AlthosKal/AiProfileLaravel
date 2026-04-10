<?php

namespace Modules\Client\Kafka\Producers;

use Illuminate\Support\Str;
use Junges\Kafka\Facades\Kafka;
use Modules\Shared\Security\InternalJwtSecurity;

/**
 * Publica una solicitud de generación de documento en el topic de comandos.
 *
 * El mensaje incluye:
 *   - correlation_id: UUID v4 para correlacionar la respuesta asíncrona
 *   - jwt: JWT RS256 interno para que laravel-mcp autentique el origen
 *   - code: script Python que el sandbox ejecutará
 *   - output_filename: nombre del archivo que el script debe producir
 *
 * El consumidor en laravel-api escucha ai-profile.business.responses y almacena
 * el resultado en Redis keyed por correlation_id.
 */
readonly class GenerateDocumentProducer
{
    private const string TOPIC = 'ai-profile.business.commands';

    public function __construct(
        private InternalJwtSecurity $jwtSecurity,
    ) {}

    /**
     * Publica el comando de generación de documento.
     *
     * @return string El correlation_id generado, para que el llamador pueda
     *                consultarlo después en Redis.
     */
    public function publish(string $email, string $code, string $outputFilename): string
    {
        $correlationId = (string) Str::uuid();
        $jwt = $this->jwtSecurity->forEmail($email);

        Kafka::publish()
            ->onTopic(self::TOPIC)
            ->withBodyKey('correlation_id', $correlationId)
            ->withBodyKey('jwt', $jwt)
            ->withBodyKey('code', $code)
            ->withBodyKey('output_filename', $outputFilename)
            ->send();

        return $correlationId;
    }
}
