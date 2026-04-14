# laravel-mcp

Servidor MCP (Model Context Protocol) de la plataforma. Gestiona las transacciones financieras de los usuarios, expone un servidor MCP para el agente IA y ejecuta scripts Python en un sandbox aislado para generar documentos (PDF, Excel, CSV).

---

## Índice

- [Responsabilidades](#responsabilidades)
- [Stack tecnológico](#stack-tecnológico)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Módulos](#módulos)
- [Rutas de la API](#rutas-de-la-api)
- [Autenticación](#autenticación)
- [Variables de entorno](#variables-de-entorno)
- [Setup local](#setup-local)
- [Tests](#tests)
- [Documentación adicional](#documentación-adicional)

---

## Responsabilidades

- Exponer una API REST protegida por JWT interno (guard `jwt-gateway`) para operaciones CRUD de transacciones
- Servir un servidor MCP (`AiAssistantServer`) al que el agente IA de `laravel-api` se conecta para consultar datos y generar documentos
- Ejecutar scripts Python generados por la IA en un sandbox Docker aislado sin acceso a red
- Almacenar los archivos generados en MinIO (S3-compatible) y proveer URLs pre-firmadas con TTL

Este servicio **no tiene tabla de usuarios propia**. La identidad del usuario siempre proviene del claim `sub` del JWT interno emitido por `laravel-api`.

---

## Stack tecnológico

| Tecnología | Versión | Uso |
|---|---|---|
| PHP | 8.3+ | Runtime |
| Laravel | 13.x | Framework |
| FrankenPHP / Octane | — | Servidor de aplicación |
| Laravel MCP | ^0.6.5 | Protocolo MCP (servidor) |
| Laravel Passport | 13.x | Guard OAuth (base para jwt-gateway) |
| lcobucci/jwt | — | Validación de JWT RS256 internos |
| Maatwebsite Excel | 3.x | Export/Import Excel y CSV |
| Spatie Laravel Data | 4.x | DTOs tipados |
| PostgreSQL 17 | + pgvector | BD de transacciones y archivos |
| Redis 7 | — | Estado de jobs sandbox, circuit breaker |
| MinIO (S3) | — | Almacenamiento de archivos generados |
| nwidart/laravel-modules | 13.x | Arquitectura modular |
| Dedoc Scramble | — | Documentación OpenAPI automática |
| Python 3 (sandbox) | — | Generación de PDFs y Excel con reportlab/openpyxl |

---

## Estructura del proyecto

```
laravel-mcp/
├── app/
│   ├── Console/Commands/         # Comandos artisan: module:make, module:make-mcp-tool, etc.
│   └── Providers/
│       └── AppServiceProvider.php  # Registra el guard jwt-gateway + Scramble
├── Modules/
│   ├── Shared/                   # Infraestructura transversal
│   └── Transaction/              # Módulo de transacciones financieras
├── config/
│   ├── auth.php                  # Guard jwt-gateway configurado aquí
│   ├── ai.php                    # Configuración del agente y clientes MCP
│   └── kafka.php                 # Configuración del broker Kafka
├── routes/
│   └── api.php                   # Ruta /v1/user de prueba de autenticación
└── docs/                         # Documentación técnica
    ├── jwt-gateway-guard.md
    ├── transaction-module.md
    ├── mcp-server.md
    └── sandbox.md
```

---

## Módulos

### Módulo `Shared`

Infraestructura transversal del servicio MCP. Incluye la seguridad, el sandbox y el almacenamiento.

| Clase | Ubicación | Propósito |
|---|---|---|
| `JwtGatewayGuard` | `app/Security/` | Guard Laravel que valida JWT RS256 internos |
| `GatewayUser` | `app/Security/` | Usuario in-memory extraído del JWT (solo email) |
| `InternalJwtValidator` | `app/Security/` | Valida JWT RS256 fuera del ciclo HTTP (jobs, consumers) |
| `CircuitBreakerAction` | `app/Actions/` | Patrón Circuit Breaker reutilizable |
| `ExecuteSandboxJob` | `app/Jobs/` | Job que ejecuta Python en el sandbox y publica resultado en Redis |
| `SandboxJobRunner` | `app/Sandbox/` | Ejecuta scripts via `docker exec` en el contenedor sandbox |
| `CloudObjectStorage` | `app/Stores/` | Sube/descarga archivos en MinIO via Laravel Storage |
| `SandboxPathBuilder` | `app/Builders/` | Construye rutas de almacenamiento para jobs sandbox |

### Módulo `Transaction`

Gestión de transacciones financieras y servidor MCP.

| Clase | Ubicación | Propósito |
|---|---|---|
| `TransactionController` | `app/Http/Controllers/` | API REST CRUD + export + import |
| `GetTransactionsAction` | `app/Actions/` | Consultas: todas, por período, por tipo, por monto |
| `AddTransactionAction` | `app/Actions/` | Crear transacción |
| `UpdateTransactionAction` | `app/Actions/` | Actualizar transacción |
| `DeleteTransactionAction` | `app/Actions/` | Eliminar transacción |
| `ExportTransactionAction` | `app/Actions/` | Generar y descargar Excel/CSV de transacciones |
| `ImportTransactionAction` | `app/Actions/` | Importar transacciones desde Excel/CSV |
| `AiAssistantServer` | `app/Mcp/Servers/` | Servidor MCP con 6 tools y 2 resources |
| `Transaction` | `app/Models/` | Modelo Eloquent con scope `forUser` |
| `File` | `app/Models/` | Registro de archivos generados/importados/exportados |

**Tools MCP del servidor `AiAssistantServer`:**

| Tool | Descripción |
|---|---|
| `GetAllTransactionsTool` | Lista paginada de todas las transacciones del usuario |
| `GetTransactionsByPeriodTool` | Transacciones en un rango de fechas |
| `GetTransactionsByAmountRangeTool` | Transacciones en un rango de montos |
| `GetTransactionByTypeTool` | Transacciones filtradas por tipo (income/expense), paginadas |
| `RequestDocumentGenerationTool` | Despacha generación de documento PDF/Excel en el sandbox |
| `CheckDocumentStatusTool` | Consulta el estado de un job de generación |

**Resources MCP:**

| Resource | URI | Descripción |
|---|---|---|
| `PdfDocumentSkillResource` | `skill://documents/pdf` | Instrucciones para generar PDFs con reportlab/matplotlib |
| `ExcelDocumentSkillResource` | `skill://documents/excel` | Instrucciones para generar Excel con openpyxl |

---

## Rutas de la API

Todas las rutas tienen prefijo `/api`.

### Raíz (`routes/api.php`)

| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `GET` | `/v1/user` | `auth:jwt-gateway` | Retorna el email del usuario autenticado (healthcheck de autenticación) |

### Módulo Transaction (`Modules/Transaction/routes/api.php`)

| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `GET` | `/v1/transactions` | `auth:jwt-gateway` | Listar transacciones paginadas |
| `POST` | `/v1/transactions` | `auth:jwt-gateway` | Crear transacción |
| `GET` | `/v1/transactions/{id}` | `auth:jwt-gateway` | Ver transacción |
| `PUT` | `/v1/transactions/{id}` | `auth:jwt-gateway` | Actualizar transacción |
| `DELETE` | `/v1/transactions/{id}` | `auth:jwt-gateway` | Eliminar transacción |
| `GET` | `/v1/transactions/export` | `auth:jwt-gateway` | Exportar a Excel/CSV |
| `POST` | `/v1/transactions/import` | `auth:jwt-gateway` | Importar desde Excel/CSV |

### Servidor MCP (`Modules/Transaction/routes/ai.php`)

| Tipo | Ruta | Middleware | Descripción |
|---|---|---|---|
| MCP Web | `/mcp/ai-assistant` | `auth:api` | Endpoint MCP via HTTP/SSE |
| MCP Local | `ai-financial-assistant` | — | Endpoint MCP para uso local |

---

## Autenticación

Todos los endpoints de la API están protegidos por el guard `jwt-gateway`. El cliente debe enviar:

```
Authorization: Bearer <JWT_RS256_emitido_por_laravel-api>
```

El guard valida:
1. Firma RS256 con `PASSPORT_PUBLIC_KEY`
2. Token no expirado (`exp` claim)
3. Emisor correcto (`iss` = `INTERNAL_API_URL`)
4. Presencia del claim `sub` (email del usuario)

No existe tabla de usuarios en este servicio. Ver [jwt-gateway-guard.md](docs/jwt-gateway-guard.md) para detalles completos.

---

## Variables de entorno

```env
# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=laravel-mcp-db
DB_USERNAME=laravel-mcp-user
DB_PASSWORD=secret

# Redis (estado de jobs sandbox)
REDIS_HOST=127.0.0.1
REDIS_PORT=6380

# Clave pública RSA de Passport (misma que en laravel-api)
PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"

# URL de laravel-api (para validar el claim iss del JWT)
INTERNAL_API_URL=http://127.0.0.1:8000

# MinIO / S3 (almacenamiento de archivos)
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=laravel-bucket
AWS_ENDPOINT=http://127.0.0.1:9002
AWS_USE_PATH_STYLE_ENDPOINT=true

# Sandbox Python
SANDBOX_JOBS_PATH=/ruta/al/volumen/mcp_sandbox_jobs
```

---

## Setup local

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Generar clave de aplicación
php artisan key:generate

# 4. Levantar infraestructura Docker
cd .. && docker compose up -d laravel-mcp-db-postgresql laravel-mcp-db-redis \
  laravel-mcp-minio mcp-sandbox-python && cd laravel-mcp

# 5. Ejecutar migraciones
php artisan migrate

# 6. (Opcional) Datos de prueba
php artisan db:seed

# 7. Levantar el servidor
php artisan serve --port=8001
```

### Comandos artisan del proyecto

```bash
# Crear módulo completo
php artisan module:make NombreModulo

# Crear Action en un módulo
php artisan module:make-action NombreModulo NombreAction

# Crear Data (DTO) en un módulo
php artisan module:make-data NombreModulo NombreData

# Crear un MCP Tool
php artisan module:make-mcp-tool NombreModulo NombreTool

# Crear un MCP Resource
php artisan module:make-mcp-resource NombreModulo NombreResource

# Crear un MCP Server
php artisan module:make-mcp-server NombreModulo NombreServer

# Crear un MCP Prompt
php artisan module:make-mcp-prompt NombreModulo NombrePrompt
```

---

## Tests

```bash
php artisan test

# Solo un test específico
php artisan test --filter CircuitBreakerActionTest
```

---

## Documentación adicional

- [JWT Gateway Guard](docs/jwt-gateway-guard.md) — Cómo funciona la validación de tokens internos
- [Módulo Transaction](docs/transaction-module.md) — CRUD, export/import, modelos y migraciones
- [Servidor MCP](docs/mcp-server.md) — Tools, resources, flujo de conexión y extensión
- [Sandbox Python](docs/sandbox.md) — Arquitectura del sandbox, ciclo de vida de un job, extensión
