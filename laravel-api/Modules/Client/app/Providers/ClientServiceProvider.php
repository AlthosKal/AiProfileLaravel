<?php

namespace Modules\Client\Providers;

use Modules\Client\Console\Commands\ConsumeDocumentResponsesCommand;
use Modules\Client\Interface\McpClientInterface;
use Modules\Client\Mcp\Client\AiAssistantMcpClient;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ClientServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Client';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'client';

    /**
     * Artisan commands registered by this module.
     *
     * @var string[]
     */
    protected array $commands = [
        ConsumeDocumentResponsesCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Register module-level bindings.
     *
     * AiAssistantMcpClient se registra como scoped para que la conexión MCP
     * se establezca una vez por request y se libere al finalizar.
     * Esto es seguro con Octane porque scoped crea una instancia nueva por request.
     */
    public function register(): void
    {
        parent::register();

        $this->app->scoped(McpClientInterface::class, AiAssistantMcpClient::class);
        $this->app->scoped(AiAssistantMcpClient::class, AiAssistantMcpClient::class);
    }
}
