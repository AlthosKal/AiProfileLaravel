# laravel-api

Servicio principal de la plataforma. Expone la API REST que consume el frontend, gestiona la autenticación de usuarios y orquesta el agente de inteligencia artificial que se comunica con `laravel-mcp` via MCP.

---

## Índice

- [Responsabilidades](#responsabilidades)
- [Stack tecnológico](#stack-tecnológico)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Módulos](#módulos)
- [Rutas de la API](#rutas-de-la-api)
- [Variables de entorno](#variables-de-entorno)
- [Setup local](#setup-local)
- [Tests](#tests)
- [Documentación adicional](#documentación-adicional)

---

## Responsabilidades

- Autenticar usuarios con Sanctum (email/password y OAuth Google)
- Gestionar el ciclo completo de seguridad: lockout progresivo, reCAPTCHA, expiración de contraseña
- Actuar como proxy HTTP hacia `laravel-mcp` para operaciones CRUD de transacciones
- Orquestar el agente IA (`AiFinancialAssistant`) conectado al servidor MCP interno y al servidor Tavily externo
- Generar y cachear JWT RS256 internos para autenticar requests a `laravel-mcp`

---

## Stack tecnológico

| Tecnología | Versión | Uso |
|---|---|---|
| PHP | 8.4 | Runtime |
| Laravel | 12.x | Framework |
| FrankenPHP | — | Servidor de aplicación (producción y dev) |
| Laravel Sanctum | — | Autenticación API stateless |
| Laravel AI | — | Agente IA con soporte MCP |
| lcobucci/jwt | 5.x | Generación de JWT RS256 internos |
| Spatie Laravel Data | — | DTOs tipados |
| Spatie Activity Log | — | Auditoría de eventos de seguridad |
| PostgreSQL 17 | + pgvector | Base de datos principal |
| Redis 7 | — | Caché de JWT internos, circuit breaker, rate limiting |
| nwidart/laravel-modules | — | Arquitectura modular |
| Dedoc Scramble | — | Documentación OpenAPI automática |
| PHPStan | nivel 8 | Análisis estático |

---

## Estructura del proyecto

```
laravel-api/
├── app/
│   ├── Actions/Module/           # Acciones del generador de módulos artisan
│   ├── Console/Commands/         # Comandos artisan: module:make, module:make-action, module:make-data
│   └── Providers/
│       └── AppServiceProvider.php  # Registra el guard jwt-gateway (para laravel-mcp, no aplica aquí)
├── Modules/
│   ├── Auth/                     # Autenticación, seguridad de usuarios
│   ├── Client/                   # Proxy HTTP, agente IA, clientes MCP
│   └── Shared/                   # Infraestructura transversal (JWT, Circuit Breaker, Rate Limiter)
├── config/                       # Configuración de Laravel + recaptchav3.php, ai.php
├── routes/                       # api.php raíz (vacío — cada módulo registra sus rutas)
└── docs/                         # Documentación técnica
    ├── authentication.md
    ├── modules.md
    ├── ai-agent.md
    └── recaptcha-circuit-breaker.md
```

---

## Módulos

### Módulo `Shared`

Infraestructura transversal usada por todos los módulos. No tiene lógica de negocio.

| Clase | Ubicación | Propósito |
|---|---|---|
| `InternalJwtSecurity` | `app/Security/` | Genera JWT RS256 internos, los cachea en Redis 5 min |
| `CircuitBreakerAction` | `app/Actions/` | Patrón Circuit Breaker reutilizable |
| `RateLimiterForApp` | `app/Security/` | Helper para configurar rate limiters por nombre |
| `BaseException` | `app/Exceptions/` | Base tipada para excepciones de dominio |
| `MiddlewaresFramework` | `app/Enums/` | Enum con alias de middlewares de Laravel |

### Módulo `Auth`

Gestión completa del ciclo de vida del usuario.

| Clase | Ubicación | Propósito |
|---|---|---|
| `LoginAction` | `app/Actions/Auth/` | Login con lockout progresivo y reCAPTCHA |
| `RegisterUserAction` | `app/Actions/Auth/` | Registro con validación de contraseña segura |
| `LockoutStateAction` | `app/Actions/Auth/` | Escala el estado de lockout (temporal → permanente) |
| `GoogleOAuthAction` | `app/Actions/OAuth/` | Autenticación OAuth Google (redirect + callback) |
| `SendPasswordResetLinkAction` | `app/Actions/Password/` | Envía email de reset de contraseña |
| `ResetPasswordAction` | `app/Actions/Password/` | Aplica el reset con validación del historial |
| `User` | `app/Models/` | Modelo con UUID, Sanctum tokens, seguridad, auditoría |
| `UserSecurityState` | `app/Models/` | Vista de base de datos con el estado de seguridad consolidado |

**Concerns del modelo `User`:**
- `HasPasswordExpiration` — expiración configurable de contraseña
- `HasSecurityStatus` — bloqueo temporal y permanente
- `HasActivityLog` — auditoría con spatie/activity-log

### Módulo `Client`

Punto de integración con `laravel-mcp` y con el proveedor de IA.

| Clase | Ubicación | Propósito |
|---|---|---|
| `AiFinancialAssistant` | `app/Ai/Agents/` | Agente IA principal (claude-sonnet-4-6, max 10 pasos) |
| `PromptAgentAction` | `app/Actions/` | Orquesta el ciclo completo de un prompt |
| `AiAssistantMcpClient` | `app/Mcp/Client/` | Cliente MCP HTTP hacia laravel-mcp |
| `TavilyMcpClient` | `app/Mcp/Client/` | Cliente MCP HTTP hacia mcp.tavily.com |
| `McpToolRegistry` | `app/Mcp/Tools/` | Mapea tools del servidor MCP a clases PHP proxy |
| `TavilyToolRegistry` | `app/Mcp/Tools/` | Mapea tools de Tavily a clases PHP proxy |
| `HttpClient` | `app/Http/LaravelMcp/Clients/` | Cliente HTTP para CRUD de transacciones vía laravel-mcp |
| `PromptInjectionMiddleware` | `app/Ai/Middleware/` | Bloquea prompt injection (2 capas: regex + heurística) |

---

## Rutas de la API

Todas las rutas tienen prefijo `/api`. Cada módulo registra las suyas en su propio `routes/`.

### Auth (`Modules/Auth/routes/auth.php`)

| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `GET` | `/v1/auth/{provider}/redirect` | — | Redirige a OAuth (Google) |
| `GET` | `/v1/auth/{provider}/callback` | — | Callback OAuth |
| `POST` | `/v1/register` | guest, rate-limit | Registro de usuario |
| `POST` | `/v1/login` | guest, rate-limit | Login con Sanctum token |
| `POST` | `/v1/forgot-password` | guest, rate-limit | Solicitar reset de contraseña |
| `POST` | `/v1/reset-password` | guest, rate-limit | Aplicar reset |
| `GET` | `/v1/verify-email/{id}/{hash}` | auth:sanctum, signed | Verificar email |
| `POST` | `/v1/email/verification-notification` | auth:sanctum | Reenviar verificación |
| `POST` | `/v1/logout` | auth:sanctum | Revocar token actual |

### Client (`Modules/Client/routes/api.php`)

| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `GET` | `/v1/transactions` | auth:sanctum | Listar transacciones (paginado) |
| `POST` | `/v1/transactions` | auth:sanctum | Crear transacción |
| `GET` | `/v1/transactions/{id}` | auth:sanctum | Ver transacción |
| `PUT` | `/v1/transactions/{id}` | auth:sanctum | Actualizar transacción |
| `DELETE` | `/v1/transactions/{id}` | auth:sanctum | Eliminar transacción |
| `GET` | `/v1/transactions/export` | auth:sanctum | Exportar a Excel/CSV |
| `POST` | `/v1/transactions/import` | auth:sanctum | Importar desde Excel/CSV |
| `POST` | `/v1/agent/prompt` | auth:sanctum | Enviar prompt al agente IA (streaming SSE) |

---

## Variables de entorno

Las variables mínimas requeridas más las que diferencian este servicio de una instalación Laravel estándar:

```env
# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel-api-db
DB_USERNAME=laravel-api-user
DB_PASSWORD=secret

# Redis (caché JWT internos, circuit breaker)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Passport — claves RSA para firmar JWT internos
PASSPORT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----"
PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"

# URL del servicio laravel-mcp (para HttpClient y AiAssistantMcpClient)
LARAVEL_MCP_URL=http://127.0.0.1:8001/api

# Proveedor de IA (Anthropic)
ANTHROPIC_API_KEY=sk-ant-...

# Tavily (búsqueda web para el agente)
TAVILY_API_KEY=tvly-...

# reCAPTCHA v3
RECAPTCHAV3_SITEKEY=...
RECAPTCHAV3_SECRET=...

# OAuth Google
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://localhost:3000/auth/google/callback
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

# 4. Levantar infraestructura Docker (PostgreSQL + Redis)
cd .. && docker compose up -d laravel-api-db-postgresql laravel-api-db-redis && cd laravel-api

# 5. Ejecutar migraciones
php artisan migrate

# 6. (Opcional) Poblar con datos de prueba
php artisan db:seed

# 7. Levantar el servidor de desarrollo con FrankenPHP
php artisan serve
# o con frankenphp directamente:
# ./frankenphp run --config Caddyfile
```

### Comandos artisan del proyecto

```bash
# Crear un nuevo módulo con estructura completa
php artisan module:make NombreModulo

# Crear una Action dentro de un módulo
php artisan module:make-action NombreModulo NombreAction

# Crear un Data (DTO de Spatie) dentro de un módulo
php artisan module:make-data NombreModulo NombreData

# Ver documentación OpenAPI generada por Scramble
# (disponible en /docs/api en entorno local)
```

---

## Tests

```bash
# Correr todos los tests
php artisan test

# Con cobertura
php artisan test --coverage

# Solo un módulo
php artisan test --filter LoginActionTest
```

La suite incluye:
- `Modules/Auth/tests/Feature/LoginActionTest.php` — login, lockout, throttling
- `Modules/Auth/tests/Feature/RecaptchaVerificationActionTest.php` — reCAPTCHA + circuit breaker
- `Modules/Auth/tests/Feature/LockoutStateActionTest.php` — escalado de lockout
- `Modules/Shared/tests/Feature/CircuitBreakerActionTest.php` — estados del circuit breaker

---

## Documentación adicional

- [Autenticación completa](docs/authentication.md) — Login, lockout progresivo, OAuth, JWT interno
- [Arquitectura modular](docs/modules.md) — Cómo están organizados los módulos, convenciones
- [Agente IA](docs/ai-agent.md) — AiFinancialAssistant, MCP clients, Tavily, prompt injection
- [reCAPTCHA + Circuit Breaker](docs/recaptcha-circuit-breaker.md) — Resiliencia ante caídas de Google
