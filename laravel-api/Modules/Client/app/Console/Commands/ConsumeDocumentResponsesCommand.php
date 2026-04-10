<?php

namespace Modules\Client\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Modules\Client\Kafka\Consumers\DocumentResponseConsumer;

/**
 * Arranca el consumer de respuestas de documentos generados por laravel-mcp.
 *
 * Gestión en producción via Supervisor:
 *
 * [program:laravel-document-response-consumer]
 * command=php /var/www/laravel-api/artisan client:consume-document-responses
 * autostart=true
 * autorestart=true
 * numprocs=1
 * redirect_stderr=true
 * stdout_logfile=/var/log/supervisor/document-response-consumer.log
 */
class ConsumeDocumentResponsesCommand extends Command
{
    protected $signature = 'client:consume-document-responses';

    protected $description = 'Consume respuestas de generación de documentos desde Kafka (ai-profile.business.responses)';

    public function handle(): void
    {
        $this->info('Iniciando consumer ai-profile.business.responses...');

        Kafka::consumer(['ai-profile.business.responses'])
            ->withHandler(new DocumentResponseConsumer)
            ->build()
            ->consume();
    }
}
