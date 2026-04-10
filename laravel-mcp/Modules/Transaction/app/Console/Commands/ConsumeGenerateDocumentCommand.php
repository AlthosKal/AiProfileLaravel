<?php

namespace Modules\Transaction\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Modules\Transaction\Kafka\Consumers\GenerateDocumentConsumer;

/**
 * Arranca el consumer de solicitudes de generación de documentos.
 *
 * Gestión en producción via Supervisor:
 *
 * [program:laravel-generate-document-consumer]
 * command=php /var/www/laravel-mcp/artisan transaction:consume-generate-document
 * autostart=true
 * autorestart=true
 * numprocs=1
 * redirect_stderr=true
 * stdout_logfile=/var/log/supervisor/generate-document-consumer.log
 */
class ConsumeGenerateDocumentCommand extends Command
{
    protected $signature = 'transaction:consume-generate-document';

    protected $description = 'Consume solicitudes de generación de documentos desde Kafka (ai-profile.business.commands)';

    public function handle(GenerateDocumentConsumer $consumer): void
    {
        $this->info('Iniciando consumer ai-profile.business.commands...');

        Kafka::consumer(['ai-profile.business.commands'])
            ->withHandler($consumer)
            ->build()
            ->consume();
    }
}
