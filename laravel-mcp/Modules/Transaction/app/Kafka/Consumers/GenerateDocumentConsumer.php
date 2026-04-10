<?php

namespace Modules\Transaction\Kafka\Consumers;

use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\Handler;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Modules\Shared\Builders\SandboxPathBuilder;
use Modules\Shared\Sandbox\SandboxJobRunner;
use Modules\Shared\Stores\CloudObjectStorage;
use Throwable;

/**
 * Consume solicitudes de generación de documentos desde laravel-api.
 *
 * Escucha el topic ai-profile.business.commands. Por cada mensaje:
 *   1. Valida el JWT RS256 interno del payload (emitido por laravel-api)
 *   2. Ejecuta el script Python en el sandbox Docker via SandboxJobRunner
 *   3. Sube el archivo generado a CloudObjectStorage (MinIO)
 *   4. Publica la URL de descarga en ai-profile.business.responses
 *
 * Estructura esperada del payload:
 * {
 *   "correlation_id": "uuid-v4",
 *   "jwt": "eyJ...",
 *   "code": "import os\n...",
 *   "output_filename": "reporte.pdf"
 * }
 */
final class GenerateDocumentConsumer implements Handler
{
    private const string RESPONSE_TOPIC = 'ai-profile.business.responses';

    public function __construct(
        private readonly SandboxJobRunner $runner,
    ) {}

    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $message->getBody();

        if (! is_array($body)) {
            Log::warning('GenerateDocumentConsumer: payload inválido', ['body' => $body]);

            return;
        }

        $correlationId = $body['correlation_id'] ?? null;
        $jwt = $body['jwt'] ?? null;
        $code = $body['code'] ?? null;
        $outputFilename = $body['output_filename'] ?? null;

        if (blank($correlationId) || blank($jwt) || blank($code) || blank($outputFilename)) {
            Log::warning('GenerateDocumentConsumer: campos requeridos faltantes', [
                'correlation_id' => $correlationId,
            ]);

            return;
        }

        if (! $this->validateJwt($jwt)) {
            Log::warning('GenerateDocumentConsumer: JWT inválido o expirado', [
                'correlation_id' => $correlationId,
            ]);

            return;
        }

        try {
            $this->process($correlationId, $code, $outputFilename);
        } catch (Throwable $e) {
            Log::error('GenerateDocumentConsumer: error procesando documento', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function process(string $correlationId, string $code, string $outputFilename): void
    {
        $job = $this->runner->run($code, $outputFilename);

        if (! $job->succeeded() || ! $job->hasOutput()) {
            Log::warning('GenerateDocumentConsumer: sandbox falló o sin output', [
                'correlation_id' => $correlationId,
                'exit_code' => $job->exitCode,
                'stdout' => $job->stdout,
            ]);

            return;
        }

        $storagePath = SandboxPathBuilder::buildForJob($job->jobId, $outputFilename);
        CloudObjectStorage::storeFromPath($storagePath, $job->outputPath);
        $downloadUrl = CloudObjectStorage::temporaryUrl($storagePath, minutes: 10);

        Kafka::publish()
            ->onTopic(self::RESPONSE_TOPIC)
            ->withBodyKey('correlation_id', $correlationId)
            ->withBodyKey('download_url', $downloadUrl)
            ->withBodyKey('filename', $outputFilename)
            ->withBodyKey('expires_in_minutes', 10)
            ->send();

        Log::info('GenerateDocumentConsumer: documento generado y respuesta publicada', [
            'correlation_id' => $correlationId,
            'filename' => $outputFilename,
        ]);
    }

    /**
     * Valida el JWT RS256 interno emitido por laravel-api.
     * Usa la clave pública de Passport para verificar la firma.
     */
    private function validateJwt(string $token): bool
    {
        try {
            $config = Configuration::forAsymmetricSigner(
                new Sha256,
                InMemory::plainText('empty'),
                InMemory::plainText(config('passport.public_key')),
            );

            $parsed = $config->parser()->parse($token);
            assert($parsed instanceof Plain);

            $config->validator()->assert(
                $parsed,
                new SignedWith($config->signer(), $config->verificationKey()),
                new LooseValidAt(new SystemClock(new DateTimeZone('UTC'))),
                new IssuedBy(config('app.internal_api_url')),
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
