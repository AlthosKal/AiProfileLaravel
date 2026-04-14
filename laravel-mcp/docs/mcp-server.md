# Servidor MCP

`laravel-mcp` expone un servidor MCP (`AiAssistantServer`) al que el agente IA de `laravel-api` se conecta para consultar transacciones financieras y generar documentos. Este documento describe la arquitectura del servidor, cada tool y resource disponible, el flujo de conexión y cómo extenderlo.

---

## Índice

- [Visión general](#visión-general)
- [AiAssistantServer](#aiassistantserver)
- [Conexión MCP](#conexión-mcp)
- [Tools](#tools)
  - [GetAllTransactionsTool](#getalltransactionstool)
  - [GetTransactionsByPeriodTool](#gettransactionsbyperiodtool)
  - [GetTransactionsByAmountRangeTool](#gettransactionsbyamountrangetool)
  - [GetTransactionByTypeTool](#gettransactionbytypetool)
  - [RequestDocumentGenerationTool](#requestdocumentgenerationtool)
  - [CheckDocumentStatusTool](#checkdocumentstatustool)
- [Resources](#resources)
  - [PdfDocumentSkillResource](#pdfdocumentskillresource)
  - [ExcelDocumentSkillResource](#exceldocumentskillresource)
- [Flujo de generación de documentos](#flujo-de-generación-de-documentos)
- [Autenticación en el servidor MCP](#autenticación-en-el-servidor-mcp)
- [Rutas MCP](#rutas-mcp)
- [Extender el servidor](#extender-el-servidor)

---

## Visión general

```
laravel-api (AiFinancialAssistant)
    │
    ├─ HTTP/SSE MCP handshake con JWT interno
    ▼
laravel-mcp/mcp/ai-assistant (AiAssistantServer)
    │
    ├─ Tools de consulta → GetTransactionsAction → PostgreSQL
    │
    └─ Tools de documentos
           │
           ├─ RequestDocumentGenerationTool
           │   └─ dispatch ExecuteSandboxJob → Redis (estado)
           │       └─ SandboxJobRunner → docker exec → mcp-sandbox-python
           │           └─ Python script → genera PDF/Excel
           │               └─ CloudObjectStorage::store → MinIO
           │
           └─ CheckDocumentStatusTool
               └─ Cache::get (Redis) → URL pre-firmada
```

---

## AiAssistantServer

**Archivo:** `Modules/Transaction/app/Mcp/Servers/AiAssistantServer.php`

```php
#[Name('Ai Financial Assistant')]
#[Version('0.0.1')]
#[Instructions('This server provides financial transaction data and analysis capabilities...')]
class AiAssistantServer extends Server
{
    protected array $tools = [
        GetTransactionsByPeriodTool::class,
        GetTransactionsByAmountRangeTool::class,
        GetAllTransactionsTool::class,
        GetTransactionByTypeTool::class,
        RequestDocumentGenerationTool::class,
        CheckDocumentStatusTool::class,
    ];

    protected array $resources = [
        PdfDocumentSkillResource::class,
        ExcelDocumentSkillResource::class,
    ];

    protected array $prompts = [
        // sin prompts por ahora
    ];
}
```

El servidor está implementado con `laravel/mcp` (`^0.6.5`). Las tools y resources se registran como arrays de `class-string` y Laravel MCP los instancia via el contenedor de dependencias, permitiendo inyección en los constructores.

---

## Conexión MCP

**Ruta Web (HTTP/SSE):** `GET|POST /mcp/ai-assistant` (middleware `auth:api`)

El cliente MCP de `laravel-api` se conecta a este endpoint con el JWT interno en el header `Authorization: Bearer {jwt}`. El servidor valida el token via el guard `api` (Passport).

**Ruta Local:** `ai-financial-assistant` — para uso con `mcp:inspect` o clientes locales.

**Proceso de handshake:**
1. El cliente envía `initialize` con su información (`name`, `version`)
2. El servidor responde con el listado de capacidades (tools, resources)
3. El cliente puede invocar tools via `tools/call` o leer resources via `resources/read`

---

## Tools

### GetAllTransactionsTool

**Clase:** `Modules/Transaction/app/Mcp/Tools/GetAllTransactionsTool.php`

Retorna todas las transacciones del usuario autenticado en formato paginado.

**Parámetros:**

| Parámetro | Tipo | Mínimo | Descripción |
|---|---|---|---|
| `per_page` | int | 1 | Transacciones por página |
| `page` | int | 1 | Número de página |

**Retorno:**
```json
{
    "data": [
        {
            "name": "Salario",
            "amount": 3000.00,
            "description": "Salario mensual",
            "type": "income",
            "created_at": "2026-04-01T10:00:00Z",
            "updated_at": "2026-04-01T10:00:00Z"
        }
    ],
    "total": 45,
    "total_amount": 12500.00,
    "per_page": 15,
    "current_page": 1,
    "last_page": 3
}
```

**Implementación:** Delega a `GetTransactionsAction::getAllByMcp()` con el `GatewayUser` extraído del request MCP.

---

### GetTransactionsByPeriodTool

**Clase:** `Modules/Transaction/app/Mcp/Tools/GetTransactionsByPeriodTool.php`

Retorna transacciones dentro de un rango de fechas.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `date_from` | date | Fecha inicial del rango (inclusive) |
| `date_to` | date | Fecha final del rango (inclusive) |

**Retorno:**
```json
{
    "data": [...],
    "transaction_count": 12,
    "total_amount": 4250.00
}
```

---

### GetTransactionsByAmountRangeTool

**Clase:** `Modules/Transaction/app/Mcp/Tools/GetTransactionsByAmountRangeTool.php`

Retorna transacciones dentro de un rango de montos.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `amount_from` | float | Monto inicial del rango (inclusive) |
| `amount_to` | float | Monto final del rango (inclusive) |

**Retorno:** Igual que `GetTransactionsByPeriodTool`.

---

### GetTransactionByTypeTool

**Clase:** `Modules/Transaction/app/Mcp/Tools/GetTransactionByTypeTool.php`

Retorna transacciones filtradas por tipo (income o expense), paginadas.

**Parámetros:**

| Parámetro | Tipo | Valores | Descripción |
|---|---|---|---|
| `type` | string | `income`, `expense` | Tipo de transacción |
| `per_page` | int | ≥ 1 | Transacciones por página |
| `page` | int | ≥ 1 | Número de página |

**Retorno:** Igual que `GetAllTransactionsTool` (respuesta paginada con `total_amount`).

---

### RequestDocumentGenerationTool

**Clase:** `Modules/Transaction/app/Mcp/Tools/RequestDocumentGenerationTool.php`

Despacha la generación de un documento (PDF, Excel, CSV) en el sandbox Python de forma asíncrona. **No espera a que el documento esté listo** — retorna inmediatamente un `job_id`.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `code` | string | Script Python completo. Debe escribir el output en `os.path.join(os.environ["OUTPUT_DIR"], output_file_name)` |
| `output_file_name` | string | Nombre del archivo a generar (ej: `"report.pdf"`, `"transactions.xlsx"`) |

**Formatos soportados:** `.pdf`, `.xlsx`, `.csv`

**Librerías Python disponibles:** `reportlab`, `openpyxl`, `matplotlib`, `pandas`, `Pillow`

**Retorno:**
```json
{
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "message": "Document generation started. Use CheckDocumentStatus with job_id '...' to track progress."
}
```

**Flujo interno:**
1. Genera un UUID como `job_id`
2. Escribe el estado `pending` en Redis (`sandbox_job:{job_id}`)
3. Despacha `ExecuteSandboxJob` a la cola de Laravel
4. Retorna el `job_id` inmediatamente

**El script Python debe seguir este patrón:**
```python
import os

output_path = os.path.join(os.environ["OUTPUT_DIR"], "report.pdf")

# ... lógica de generación ...

# Siempre escribir el archivo en output_path
doc.build(story)  # PDF con reportlab
# o
wb.save(output_path)  # Excel con openpyxl
```

Los resources `PdfDocumentSkillResource` y `ExcelDocumentSkillResource` proveen instrucciones detalladas y plantillas de código que el agente puede consultar antes de generar los scripts.

---

### CheckDocumentStatusTool

**Clase:** `Modules/Transaction/app/Mcp/Tools/CheckDocumentStatusTool.php`

Consulta el estado actual de un job de generación de documentos.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `job_id` | string | El UUID retornado por `RequestDocumentGenerationTool` |

**Posibles estados:**

| Status | Descripción |
|---|---|
| `pending` | El job está en cola, aún no procesado |
| `processing` | El sandbox está ejecutando el script Python |
| `completed` | El documento está listo. Incluye `download_url` (10 min de TTL) |
| `failed` | El script falló. Incluye `error_message` con la causa |

**Retorno (completed):**
```json
{
    "job_id": "550e8400-...",
    "status": "completed",
    "download_url": "https://minio.../sandbox/generated/job_abc/report.pdf?token=...",
    "file_name": "report.pdf",
    "expired_in_minutes": 10,
    "error_message": null
}
```

**Retorno (failed):**
```json
{
    "job_id": "550e8400-...",
    "status": "failed",
    "download_url": null,
    "file_name": null,
    "expired_in_minutes": null,
    "error_message": "Script execution error: ModuleNotFoundError: No module named 'seaborn'"
}
```

**Estrategia de polling:** El agente debe llamar `CheckDocumentStatusTool` repetidamente con intervalos de espera hasta que el status sea `completed` o `failed`. Los resultados se retienen 10 minutos en Redis tras la finalización.

---

## Resources

Los resources MCP son documentos de referencia que el agente puede leer para obtener instrucciones sobre cómo usar las tools de generación de documentos.

### PdfDocumentSkillResource

**Clase:** `Modules/Transaction/app/Mcp/Resources/PdfDocumentSkillResource.php`
**URI:** `skill://documents/pdf`

Provee instrucciones detalladas y plantillas de código Python para generar PDFs con `reportlab` y `matplotlib`. Incluye:

- Estructura básica del documento (header fijo, estilo corporativo)
- Cómo agregar tablas con estilos
- Cómo incrustar gráficos de matplotlib en el PDF
- Reglas de uso (siempre incluir el header, nombres de archivos con `.pdf`, etc.)

### ExcelDocumentSkillResource

**Clase:** `Modules/Transaction/app/Mcp/Resources/ExcelDocumentSkillResource.php`
**URI:** `skill://documents/excel`

Provee instrucciones para generar archivos Excel (`.xlsx`) con `openpyxl`. Incluye:

- Estructura del workbook (header fijo, estilos predefinidos)
- Cómo escribir tablas de datos con estilos alternados
- Cómo agregar gráficos nativos de openpyxl
- Cómo crear múltiples hojas (Summary + Data)
- Auto-ajuste de anchos de columna

---

## Flujo de generación de documentos

```
Agente IA (laravel-api)
    │
    ├─ 1. Lee skill://documents/pdf o skill://documents/excel
    │      para obtener instrucciones y plantillas de código
    │
    ├─ 2. Consulta datos con las tools de transacciones
    │      (GetAllTransactions, GetByPeriod, etc.)
    │
    ├─ 3. Llama RequestDocumentGenerationTool con:
    │      - code: script Python que usa los datos del paso 2
    │      - output_file_name: "reporte_enero.pdf"
    │
    ├─ 4. Recibe job_id = "uuid-..."
    │
    ├─ 5. Llama CheckDocumentStatusTool(job_id) en polling
    │      hasta que status == "completed" o "failed"
    │
    └─ 6. Retorna la download_url al usuario (válida 10 min)

Mientras tanto en laravel-mcp:
    │
    ├─ ExecuteSandboxJob::dispatch(jobId, code, email)
    │
    ├─ Redis: sandbox_job:{jobId} = {status: "processing"}
    │
    ├─ SandboxJobRunner::run(data)
    │   ├─ crea /sandbox/jobs/{jobId}/script.py
    │   ├─ docker exec mcp-sandbox-python timeout 60 python script.py
    │   └─ lee /sandbox/jobs/{jobId}/output/{output_file_name}
    │
    ├─ CloudObjectStorage::storeFromPath(storagePath, localPath)
    │   └─ sube a MinIO en sandbox/generated/{jobId}/{file}
    │
    ├─ File::create(user_email, name, path, type: GENERATED)
    │
    ├─ CloudObjectStorage::temporaryUrl(path, minutes: 10)
    │
    └─ Redis: sandbox_job:{jobId} = {status: "completed", download_url: "..."}
```

---

## Autenticación en el servidor MCP

La ruta web del servidor MCP (`/mcp/ai-assistant`) usa `middleware('auth:api')` (Passport). `laravel-api` adjunta el JWT interno en el header `Authorization: Bearer {jwt}`, que es validado por el guard `jwt-gateway` de Laravel Passport.

Una vez autenticado, el objeto `$request->user()` dentro de las tools retorna un `GatewayUser` con el email del usuario. Todas las tools pasan este `GatewayUser` a las acciones para garantizar el aislamiento de datos.

```php
public function handle(Request $request, GetTransactionsByMcpRequestData $data): ResponseFactory
{
    /** @var GatewayUser $user */
    $user = $request->user();  // GatewayUser extraído del JWT interno

    $result = $this->action->getAllByMcp($data, $user);
    // ...
}
```

---

## Rutas MCP

**Archivo:** `Modules/Transaction/routes/ai.php`

```php
// Endpoint web (HTTP/SSE) — usado por laravel-api
Mcp::web('/mcp/ai-assistant', AiAssistantServer::class)
    ->middleware('auth:api');

// Endpoint local — para mcp:inspect o desarrollo
Mcp::local('ai-financial-assistant', AiAssistantServer::class);
```

---

## Extender el servidor

### Agregar una nueva tool

1. **Crear la tool:**

```bash
php artisan module:make-mcp-tool Transaction GetTransactionsSummaryTool
```

2. **Implementar la lógica** en `Modules/Transaction/app/Mcp/Tools/GetTransactionsSummaryTool.php`:

```php
#[Title('Get Transactions Summary')]
#[Description('Returns a financial summary of the user transactions...')]
class GetTransactionsSummaryTool extends Tool
{
    public function __construct(
        private readonly GetTransactionsAction $action,
    ) {}

    public function handle(Request $request, SummaryRequestData $data): ResponseFactory
    {
        /** @var GatewayUser $user */
        $user = $request->user();
        $result = $this->action->getSummary($user);

        return Response::make(Response::text('Summary retrieved.'))
            ->withStructuredContent($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return SummaryRequestData::toolSchema($schema);
    }
}
```

3. **Registrar en el servidor:**

```php
// AiAssistantServer.php
protected array $tools = [
    // ... tools existentes ...
    GetTransactionsSummaryTool::class,
];
```

4. **Registrar en laravel-api** (para que el agente la pueda usar):
   - Crear proxy en `Modules/Client/app/Ai/Tools/GetTransactionsSummaryTool.php`
   - Agregar a `McpToolRegistry::TOOL_MAP`

### Agregar un nuevo resource

```bash
php artisan module:make-mcp-resource Transaction CsvDocumentSkillResource
```

```php
#[Uri('skill://documents/csv')]
#[Description('Instructions for generating CSV files.')]
class CsvDocumentSkillResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text($this->skill());
    }

    private function skill(): string
    {
        return '...instrucciones...';
    }
}
```

Registrar en `AiAssistantServer::$resources`.
