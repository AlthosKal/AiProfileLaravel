<?php

namespace App\Listeners;

use App\Actions\Module\CleanResourcesAction;
use App\Actions\Module\FixComposerJsonAction;
use App\Actions\Module\FixModuleJsonAction;
use App\Actions\Module\FixNamespacesAction;
use App\Actions\Module\FixReturnTypesAction;
use App\Actions\Module\FixRouteFilesAction;
use App\Actions\Module\FixServiceProviderDocBlocksAction;
use App\Actions\Module\FixServiceProviderRegisterViewsAction;
use App\Actions\Module\RemoveFrontendFilesAction;
use App\Actions\Module\RunComposerDumpAutoloadAction;
use App\Actions\Module\RunLastanAction;
use App\Actions\Module\RunPintAction;
use App\Events\ModuleCreatedEvent;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

/**
 * Listener que formatea y corrige automáticamente el código de un módulo recién creado.
 */
readonly class ModuleCreatedListener
{
    public function __construct(
        private FixNamespacesAction $fixNamespaces,
        private FixComposerJsonAction $fixComposerJson,
        private FixModuleJsonAction $fixModuleJson,
        private FixRouteFilesAction $fixRouteFiles,
        private FixServiceProviderDocBlocksAction $fixServiceProviderDocBlocks,
        private FixServiceProviderRegisterViewsAction $fixServiceProviderRegisterViews,
        private FixReturnTypesAction $fixReturnTypes,
        private CleanResourcesAction $cleanResources,
        private RemoveFrontendFilesAction $removeFrontendFiles,
        private RunPintAction $runPint,
        private RunComposerDumpAutoloadAction $runComposerDumpAutoload,
        private RunLastanAction $runLastan,
    ) {}

    /**
     * @throws FileNotFoundException
     */
    public function handle(ModuleCreatedEvent $event): void
    {
        echo "\033[0;36m🚀 Iniciando formateo automático del módulo: $event->moduleName\033[0m\n";

        $this->fixNamespaces->handle($event->modulePath, $event->moduleName);
        $this->fixComposerJson->handle($event->modulePath, $event->moduleName);
        $this->fixModuleJson->handle($event->modulePath, $event->moduleName);
        $this->fixRouteFiles->handle($event->modulePath, $event->moduleName);
        $this->fixServiceProviderDocBlocks->handle($event->modulePath, $event->moduleName);
        $this->fixServiceProviderRegisterViews->handle($event->modulePath, $event->moduleName);
        $this->fixReturnTypes->handle($event->modulePath);
        $this->cleanResources->handle($event->modulePath);
        $this->removeFrontendFiles->handle($event->modulePath);
        $this->runPint->handle($event->modulePath);
        $this->runComposerDumpAutoload->handle();
        $this->runLastan->handle($event->modulePath);

        echo "\033[0;32m✅ Módulo $event->moduleName formateado y validado correctamente\033[0m\n";
    }
}
