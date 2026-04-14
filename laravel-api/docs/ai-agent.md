# Agente IA

`laravel-api` aloja un agente de inteligencia artificial (`AiFinancialAssistant`) que permite a los usuarios consultar sus transacciones financieras en lenguaje natural, solicitar reportes y buscar información en tiempo real. El agente usa `claude-sonnet-4-6` como modelo base y se comunica con `laravel-mcp` via MCP y con Tavily para búsqueda web.

---

## Índice

- [Visión general](#visión-general)
- [Flujo completo de un prompt](#flujo-completo-de-un-prompt)
- [AiFinancialAssistant](#aifinancialsassistant)
- [PromptAgentAction](#promptagentaction)
- [Clientes MCP](#clientes-mcp)
  - [AiAssistantMcpClient (laravel-mcp)](#aiassistantmcpclient-laravel-mcp)
  - [TavilyMcpClient (búsqueda web)](#tavilymcpclient-búsqueda-web)
- [Tool Registries](#tool-registries)
- [Herramientas disponibles](#herramientas-disponibles)
- [Prompt Injection Middleware](#prompt-injection-middleware)
- [Gestión de conversaciones](#gestión-de-conversaciones)
- [Endpoint](#endpoint)
- [Configuración](#configuración)
- [Extender el agente](#extender-el-agente)

---

## Visión general

```
POST /api/v1/agent/prompt
    │
    ├─ AgentController::prompt()
    │
    ├─ PromptAgentAction::execute()
    │   ├─ AiAssistantMcpClient::connectForUser($email)
    │   │   └─ InternalJwtSecurity::forEmail($email) → JWT RS256
    │   │       └─ HTTP MCP handshake → laravel-mcp/mcp/ai-assistant
    │   │
    │   ├─ TavilyMcpClient::connectToTavily()
    │   │   └─ HTTP MCP handshake → mcp.tavily.com/mcp/?tavilyApiKey=...
    │   │
    │   ├─ AiFinancialAssistant ($mcpClient, $tavilyMcpClient)
    │   │   ├─ instructions() → AiAssistantServerPrompt.md
    │   │   ├─ tools() → McpToolRegistry + TavilyToolRegistry
    │   │   └─ middleware() → PromptInjectionMiddleware
    │   │
    │   └─ agent->stream($message) → StreamableAgentResponse (SSE)
    │
    └─ response()->withVercelDataProtocol() → SSE al frontend
```

---

## Flujo completo de un prompt

1. El frontend envía `POST /api/v1/agent/prompt` con el mensaje y opcionalmente un `conversation_id`
2. `AgentController` extrae el `User` autenticado (Sanctum) y delega a `PromptAgentAction`
3. `PromptAgentAction` conecta los dos clientes MCP simultáneamente:
   - `AiAssistantMcpClient.connectForUser(email)` — genera/reutiliza JWT interno y establece sesión MCP
   - `TavilyMcpClient.connectToTavily()` — conecta al servidor Tavily con la API key
4. Se instancia `AiFinancialAssistant` con los clientes conectados
5. Si hay `conversation_id`, el agente continúa la conversación existente; si no, inicia una nueva asociada al usuario
6. `agent->stream(message)` envía el prompt a `claude-sonnet-4-6` con las tools disponibles
7. El LLM puede invocar tools (máximo 10 pasos), cada invocación pasa por el cliente MCP correspondiente
8. La respuesta se hace streaming via SSE usando el protocolo Vercel Data

---

## AiFinancialAssistant

**Archivo:** `Modules/Client/app/Ai/Agents/AiFinancialAssistant.php`

```php
#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
#[MaxSteps(10)]
class AiFinancialAssistant implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;
}
```

**Contratos implementados:**

| Contrato | Propósito |
|---|---|
| `Agent` | Define el agente como un agente de IA |
| `Conversational` | Habilita memoria de conversación multi-turno |
| `HasMiddleware` | Permite registrar middleware de pre-procesamiento |
| `HasTools` | Registra herramientas disponibles para el LLM |

**`instructions()`:** Lee el system prompt desde `Modules/Client/resources/prompts/servers/AiAssistantServerPrompt.md`. El prompt define el rol, las capacidades y las restricciones del agente.

**`tools()`:** Descubre dinámicamente las tools de ambos clientes MCP y las envuelve en las clases proxy correspondientes via los Tool Registries.

---

## PromptAgentAction

**Archivo:** `Modules/Client/app/Actions/PromptAgentAction.php`

Orquesta el ciclo completo: conexión MCP, instanciación del agente, decisión de conversación y streaming.

```php
public function execute(PromptRequestData $data, User $user): StreamableAgentResponse
{
    $this->mcpClient->connectForUser($user->email);
    $this->tavilyMcpClient->connectToTavily();

    $agent = new AiFinancialAssistant($this->mcpClient, $this->tavilyMcpClient);

    if ($data->conversation_id !== null) {
        $agent->continue($data->conversation_id, as: $user);
    } else {
        $agent->forUser($user);
    }

    return $agent->stream($data->message);
}
```

**DTO de entrada:** `PromptRequestData`

| Campo | Tipo | Descripción |
|---|---|---|
| `message` | `string` | Prompt del usuario |
| `conversation_id` | `?string` | UUID de conversación existente (null = nueva) |

---

## Clientes MCP

### AiAssistantMcpClient (laravel-mcp)

**Archivo:** `Modules/Client/app/Mcp/Client/AiAssistantMcpClient.php`

Cliente MCP HTTP que se conecta al servidor `AiAssistantServer` de `laravel-mcp`. Cada conexión incluye el JWT interno del usuario en el header `Authorization`.

**Ciclo de vida:**
```
1. connectForUser($email)
   ├─ InternalJwtSecurity::forEmail($email) → JWT (cacheado 5 min en Redis)
   ├─ Endpoint: {LARAVEL_MCP_URL}/mcp/ai-assistant
   └─ Client::builder() → MCP handshake con Bearer JWT

2. listTools() → expone las tools al agente
3. callTool($name, $arguments) → invoca una tool durante la respuesta del LLM

4. disconnect() → cierra la sesión MCP
```

**Configuración del cliente MCP:**
```php
Client::builder()
    ->setClientInfo(name: 'ai-profile-client', version: '1.0.0')
    ->setInitTimeout(30)        // segundos para el handshake inicial
    ->setRequestTimeout(120)    // segundos por invocación de tool
    ->setMaxRetries(3)          // reintentos ante fallos de red
    ->build();
```

### TavilyMcpClient (búsqueda web)

**Archivo:** `Modules/Client/app/Mcp/Client/TavilyMcpClient.php`

Cliente MCP HTTP que se conecta al servidor MCP público de Tavily. La API key se pasa como query parameter en la URL (requerido por Tavily).

```
Endpoint: https://mcp.tavily.com/mcp/?tavilyApiKey={TAVILY_API_KEY}
```

**Tools disponibles en Tavily:**

| Tool | Descripción |
|---|---|
| `tavily-search` | Búsqueda web semántica en tiempo real |
| `tavily-extract` | Extrae el contenido de una URL |
| `tavily-map` | Mapea el sitemap de un dominio |
| `tavily-crawl` | Navega y extrae contenido de múltiples páginas |

---

## Tool Registries

Los registries resuelven la clase PHP proxy que corresponde a cada tool MCP. Laravel AI usa `class_basename($tool)` para identificar tools en las respuestas del LLM, por lo que cada proxy debe tener un nombre de clase que coincida exactamente con el nombre de la tool en el servidor.

### McpToolRegistry

**Archivo:** `Modules/Client/app/Mcp/Tools/McpToolRegistry.php`

```
'GetAllTransactionsTool'           → GetAllTransactionsTool::class
'GetTransactionsByPeriodTool'      → GetTransactionsByPeriodTool::class
'GetTransactionsByAmountRangeTool' → GetTransactionsByAmountRangeTool::class
'GetTransactionByTypeTool'         → GetTransactionByTypeTool::class
'RequestDocumentGenerationTool'    → RequestDocumentGenerationTool::class
'CheckDocumentStatusTool'          → CheckDocumentStatusTool::class
```

### TavilyToolRegistry

**Archivo:** `Modules/Client/app/Mcp/Tools/TavilyToolRegistry.php`

```
'tavily-search'  → TavilySearchTool::class
'tavily-extract' → TavilyExtractTool::class
'tavily-map'     → TavilyMapTool::class
'tavily-crawl'   → TavilyCrawlTool::class
```

---

## Herramientas disponibles

El agente tiene acceso a las siguientes herramientas durante una conversación:

### Tools internas (laravel-mcp)

| Tool | Parámetros | Descripción |
|---|---|---|
| `GetAllTransactionsTool` | `per_page`, `page` | Lista paginada de todas las transacciones del usuario |
| `GetTransactionsByPeriodTool` | `date_from`, `date_to` | Transacciones en un rango de fechas |
| `GetTransactionsByAmountRangeTool` | `amount_from`, `amount_to` | Transacciones en un rango de montos |
| `GetTransactionByTypeTool` | `type`, `per_page`, `page` | Transacciones por tipo (income/expense), paginadas |
| `RequestDocumentGenerationTool` | `code` (Python), `output_file_name` | Genera PDF/Excel/CSV en sandbox y retorna `job_id` |
| `CheckDocumentStatusTool` | `job_id` | Consulta estado del job: pending/processing/completed/failed |

### Tools externas (Tavily)

| Tool | Descripción |
|---|---|
| `tavily-search` | Búsqueda web en tiempo real para contexto financiero |
| `tavily-extract` | Extrae texto de una URL específica |
| `tavily-map` | Mapea la estructura de un sitio web |
| `tavily-crawl` | Navega múltiples páginas de un dominio |

---

## Prompt Injection Middleware

**Archivo:** `Modules/Client/app/Ai/Middleware/PromptInjectionMiddleware.php`

El middleware bloquea intentos de manipulación del agente antes de que el prompt llegue al proveedor de IA. Corre en dos capas en cascada:

### Capa 1 — Pattern Matching (regex, ~0.1ms)

Detecta patrones literales conocidos de prompt injection:

| Categoría | Ejemplo detectado |
|---|---|
| Ignorar instrucciones | `"ignore previous instructions"`, `"forget your rules"` |
| Escape de rol (DAN) | `"you are now"`, `"act as"`, `"pretend to be"`, `"jailbreak"` |
| Inyección de contexto | `<system>`, `###system`, `[INST]`, `<\|im_start\|>` |
| Extracción del prompt | `"repeat your system prompt"`, `"reveal your instructions"` |
| Bypass de restricciones | `"bypass your filter"`, `"override your rules"` |
| Inyección de turno | `\nassistant:`, `\nsystem:` |

### Capa 2 — Heurística (~0.1ms)

Detecta técnicas de obfuscación que evaden las regex literales:

| Técnica | Detección |
|---|---|
| Base64 | Bloques `[A-Za-z0-9+/]{40,}={0,2}` — indica payload codificado |
| Homóglifos Unicode | Caracteres fuera del rango latin extendido (U+0000–U+024F) |
| Espaciado anómalo | 6+ letras individuales separadas por espacios (`i g n o r e`) |
| Densidad de símbolos | `<>{}\[\]` > 15% del total de caracteres |

**En caso de detección:**
- Log en canal `stack` con capa, razón y preview del prompt (truncado a 120 chars)
- Lanza `PromptInjectionException` (HTTP 422)
- El prompt nunca llega al proveedor de IA

---

## Gestión de conversaciones

El agente implementa `Conversational` y usa `RemembersConversations` de Laravel AI. Las conversaciones se almacenan en la tabla `agent_conversations`.

**Migración:** `Modules/Client/database/migrations/2026_04_10_125318_create_agent_conversations_table.php`

**Flujo:**
- Sin `conversation_id` → `agent->forUser($user)` — nueva conversación asociada al usuario
- Con `conversation_id` → `agent->continue($id, as: $user)` — recupera el historial y continúa

---

## Endpoint

**`POST /api/v1/agent/prompt`**

**Middleware:** `auth:sanctum`

**Request:**
```json
{
    "message": "¿Cuánto gasté en el último mes?",
    "conversation_id": null
}
```

**Response:** Server-Sent Events (SSE) en formato Vercel Data Protocol

```
data: {"type":"text","text":"En el último mes tus gastos fueron..."}
data: {"type":"tool_call","name":"GetTransactionsByPeriodTool","args":{...}}
data: {"type":"tool_result","content":{...}}
data: {"type":"finish","conversation_id":"uuid-..."}
```

---

## Configuración

**`config/ai.php`**

```php
return [
    'mcp-client' => [
        'name'           => env('MCP_CLIENT_NAME', 'ai-profile-client'),
        'version'        => env('MCP_CLIENT_VERSION', '1.0.0'),
        'description'    => env('MCP_CLIENT_DESCRIPTION'),
        'init-timeout'   => env('MCP_CLIENT_INIT_TIMEOUT', 30),
        'request-timeout'=> env('MCP_CLIENT_REQUEST_TIMEOUT', 120),
        'max-retries'    => env('MCP_CLIENT_MAX_RETRIES', 3),
    ],
    'tavily' => [
        'api_key' => env('TAVILY_API_KEY'),
        'url'     => env('TAVILY_MCP_URL', 'https://mcp.tavily.com/mcp/'),
    ],
];
```

**Variables de entorno requeridas:**

| Variable | Descripción |
|---|---|
| `ANTHROPIC_API_KEY` | API key de Anthropic para claude-sonnet-4-6 |
| `TAVILY_API_KEY` | API key de Tavily para búsqueda web |
| `LARAVEL_MCP_URL` | URL base de laravel-mcp (ej: `http://localhost:8001/api`) |

---

## Extender el agente

### Agregar una nueva tool interna

1. En `laravel-mcp`: Crear la tool en `Modules/Transaction/app/Mcp/Tools/NuevaTool.php`
2. En `laravel-mcp`: Registrarla en `AiAssistantServer::$tools`
3. En `laravel-api`: Crear el proxy en `Modules/Client/app/Ai/Tools/NuevaTool.php`:

```php
class NuevaTool extends McpProxyTool
{
    // El nombre de la clase debe coincidir exactamente con el nombre MCP
}
```

4. En `laravel-api`: Registrar en `McpToolRegistry::TOOL_MAP`:

```php
'NuevaTool' => NuevaTool::class,
```

### Agregar un nuevo proveedor de búsqueda

1. Crear un cliente MCP: implementar `McpClientInterface`
2. Crear un Tool Registry al estilo de `TavilyToolRegistry`
3. Crear las subclases proxy (extendiendo `TavilyMcpProxyTool` o una nueva base)
4. Conectar el cliente en `PromptAgentAction::execute()`
5. Añadir las tools del nuevo cliente en `AiFinancialAssistant::tools()`

### Modificar el system prompt

Editar `Modules/Client/resources/prompts/servers/AiAssistantServerPrompt.md`. El archivo se lee en cada request (no está cacheado), por lo que los cambios son inmediatos sin necesidad de reiniciar el servidor.
