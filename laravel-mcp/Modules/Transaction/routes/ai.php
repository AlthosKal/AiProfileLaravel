<?php

use Laravel\Mcp\Facades\Mcp;
use Modules\Transaction\Mcp\Servers\AiAssistantServer;

Mcp::web('/mcp/ai-assistant', AiAssistantServer::class)
    ->middleware('auth:api');
