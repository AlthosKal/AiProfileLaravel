<?php

namespace Modules\Transaction\Kafka\Consumers;

use Exception;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\Handler;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Modules\Shared\Actions\ExecuteSandboxAction;
use Modules\Shared\Enums\JobErrorType;
use Modules\Shared\Http\Data\ExecuteSandboxRequestData;
use Modules\Shared\Security\GatewayUser;
use Modules\Shared\Security\InternalJwtValidator;
use Modules\Transaction\Http\Data\GenerateDocumentRequestData;

/**
 * Consume solicitudes de generación de documentos desde laravel-api.
 *
 * Escucha el topic ai-profile.business.commands. Por cada mensaje:
 *   1. Válida el JWT RS256 interno del payload (emitido por laravel-api)
 *   2. Ejecuta el script Python en el sandbox via ExecuteSandboxAction
 *   3. Publica el resultado (URL o error) en ai-profile.business.responses
 *
 * La validación del JWT es necesaria en este contexto porque el consumer
 * corre fuera del ciclo HTTP — no existe guard ni middleware que lo haga.
 * En requests HTTP la válida JwtGatewayGuard automáticamente.
 */
final class GenerateDocumentConsumer implements Handler
{
    private const string RESPONSE_TOPIC = 'ai-profile.business.responses';

    public function __construct(
        private readonly ExecuteSandboxAction $action,
        private readonly InternalJwtValidator $jwtValidator,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $data = GenerateDocumentRequestData::from($message->getBody());

        $email = $this->jwtValidator->validate($data->jwt);

        if ($email === null) {
            Log::warning('GenerateDocumentConsumer: JWT inválido o expirado', [
                'correlation_id' => $data->correlation_id,
            ]);

            return;
        }

        $sandboxData = new ExecuteSandboxRequestData(
            code: $data->code,
            output_file_name: $data->output_filename,
        );

        $result = $this->action->execute($sandboxData, new GatewayUser(email: $email));

        if ($result->errorType !== JobErrorType::NO_ERROR) {
            Log::warning('GenerateDocumentConsumer: sandbox falló', [
                'correlation_id' => $data->correlation_id,
                'error_type' => $result->errorType->value,
                'error_message' => $result->errorMessage,
            ]);

            return;
        }

        Kafka::publish()
            ->onTopic(self::RESPONSE_TOPIC)
            ->withBodyKey('correlation_id', $data->correlation_id)
            ->withBodyKey('download_url', $result->downloadUrl)
            ->withBodyKey('filename', $result->fileName)
            ->withBodyKey('expires_in_minutes', $result->expiredInMinutes)
            ->send();

        Log::info('GenerateDocumentConsumer: documento generado y respuesta publicada', [
            'correlation_id' => $data->correlation_id,
            'filename' => $result->fileName,
        ]);
    }
}
