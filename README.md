# AiProfileLaravel

Monorepo de una plataforma financiera personal potenciada por IA. Compuesto por dos servicios Laravel independientes que se comunican mediante JWT interno y el protocolo MCP (Model Context Protocol).

---

## Servicios

| Servicio | Puerto (dev) | Rol |
|---|---|---|
| `laravel-api` | `8000` | API principal — autenticación, proxy de datos, agente IA |
| `laravel-mcp` | `8001` | Servidor MCP — datos, herramientas y generación de documentos |

---

## Arquitectura general

```
┌─────────────────────────────────────────────────────────────┐
│  Frontend (React / Next.js)                                 │
│  - Autenticación via Sanctum tokens                         │
│  - Streaming SSE del agente IA                              │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS + Sanctum Bearer Token
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  laravel-api  (puerto 8000)                                 │
│                                                             │
│  Módulo Auth   → Login, registro, OAuth Google,             │
│                  lockout progresivo, reCAPTCHA              │
│  Módulo Client → Proxy HTTP → laravel-mcp                   │
│                  Agente IA (claude-sonnet-4-6)              │
│                  Clientes MCP (interno + Tavily)            │
│  Módulo Shared → InternalJwtSecurity, CircuitBreaker        │
└────────────────┬────────────────────────────────────────────┘
                 │ JWT RS256 interno (5 min, caché Redis)
                 │ Protocolo MCP sobre HTTP/SSE
                 ▼
┌─────────────────────────────────────────────────────────────┐
│  laravel-mcp  (puerto 8001)                                 │
│                                                             │
│  Módulo Transaction → CRUD, export/import Excel/CSV         │
│  Módulo Shared      → JwtGatewayGuard, Sandbox, MinIO       │
│  Servidor MCP       → AiAssistantServer (6 tools)           │
│  Python Sandbox     → Generación de PDFs y Excel            │
└─────────────────────────────────────────────────────────────┘
                 │ Tavily MCP (HTTPS)
                 ▼
┌──────────────────────────┐
│  mcp.tavily.com/mcp/     │
│  - tavily-search         │
│  - tavily-extract        │
│  - tavily-map            │
│  - tavily-crawl          │
└──────────────────────────┘
```

---

## Infraestructura (Docker)

Todos los servicios de soporte están definidos en `compose.yaml`:

| Contenedor | Imagen | Puerto host | Propósito |
|---|---|---|---|
| `laravel-api-db-postgresql` | `pgvector/pgvector:pg17` | `5432` | BD principal (usuarios, tokens, seguridad) |
| `laravel-mcp-db-postgresql` | `pgvector/pgvector:pg17` | `5433` | BD de laravel-mcp (transacciones, archivos) |
| `laravel-api-db-redis` | `redis:7-alpine` | `6379` | Caché JWT, circuit breaker, sesiones (api) |
| `laravel-mcp-db-redis` | `redis:7-alpine` | `6380` | Estado de jobs sandbox (mcp) |
| `kafka` | `apache/kafka:4.0.2` | `9092` | Broker de mensajes (modo KRaft) |
| `kafka-ui` | `provectuslabs/kafka-ui` | `8090` | Panel web de Kafka |
| `laravel-mcp-minio` | `quay.io/minio/minio` | `9002/9003` | Object storage S3-compatible (archivos generados) |
| `mcp-sandbox-python` | `mcp-sandbox-python` (local) | — | Sandbox aislado para ejecución de Python |

### Red y volúmenes

Todos los contenedores comparten la red `laravel_api_network` (`172.28.0.0/16`). El sandbox (`mcp-sandbox-python`) corre con `network_mode: none` — sin acceso a Internet — y comparte el volumen `mcp_sandbox_jobs` con el host para recibir scripts y devolver archivos generados.

### Levantar la infraestructura

```bash
# Desde la raíz del monorepo
docker compose up -d

# Solo infraestructura (sin los servicios Laravel si los corres localmente)
docker compose up -d laravel-api-db-postgresql laravel-mcp-db-postgresql \
  laravel-api-db-redis laravel-mcp-db-redis kafka kafka-ui laravel-mcp-minio \
  mcp-sandbox-python
```

### Variables de entorno Docker

| Archivo | Qué configura |
|---|---|
| `.env.docker.api.postgresql` | Usuario y BD de PostgreSQL para laravel-api |
| `.env.docker.mcp.postgresql` | Usuario y BD de PostgreSQL para laravel-mcp |
| `.env.docker.kafka` | Configuración del broker Kafka (KRaft) |
| `.env.docker.minio` | Credenciales de MinIO y nombre de bucket |

---

## Comunicación entre servicios

### JWT RS256 interno

`laravel-api` genera tokens JWT firmados con RS256 para autenticar cada request a `laravel-mcp`. El par de claves es el mismo que usa Laravel Passport:

- **laravel-api** firma con `PASSPORT_PRIVATE_KEY`
- **laravel-mcp** valida con `PASSPORT_PUBLIC_KEY`
- TTL: 5 minutos, cacheado en Redis por email

Ver documentación detallada en:
- [`laravel-api/docs/authentication.md`](laravel-api/docs/authentication.md)
- [`laravel-mcp/docs/jwt-gateway-guard.md`](laravel-mcp/docs/jwt-gateway-guard.md)

### Protocolo MCP

El agente IA en `laravel-api` se conecta a `laravel-mcp` usando el [Model Context Protocol](https://modelcontextprotocol.io/) sobre HTTP/SSE. El cliente MCP adjunta el JWT interno como Bearer token en cada sesión.

Ver documentación detallada en:
- [`laravel-api/docs/ai-agent.md`](laravel-api/docs/ai-agent.md)
- [`laravel-mcp/docs/mcp-server.md`](laravel-mcp/docs/mcp-server.md)

---

## Estructura del monorepo

```
AiProfileLaravel/
├── compose.yaml                  # Infraestructura Docker completa
├── docker/
│   └── sandbox-python/           # Dockerfile del sandbox Python aislado
├── .env.docker.api.postgresql    # Variables PostgreSQL para laravel-api
├── .env.docker.mcp.postgresql    # Variables PostgreSQL para laravel-mcp
├── .env.docker.kafka             # Variables Kafka
├── .env.docker.minio             # Variables MinIO
├── laravel-api/                  # Servicio principal (ver su README)
└── laravel-mcp/                  # Servidor MCP (ver su README)
```

---

## Documentación por servicio

### laravel-api
- [README](laravel-api/README.md) — Setup, módulos, comandos Artisan
- [Autenticación](laravel-api/docs/authentication.md) — Login, lockout, OAuth, JWT interno
- [Arquitectura modular](laravel-api/docs/modules.md) — Módulos, convenciones, cómo extender
- [Agente IA](laravel-api/docs/ai-agent.md) — AiFinancialAssistant, MCP clients, Tavily
- [reCAPTCHA + Circuit Breaker](laravel-api/docs/recaptcha-circuit-breaker.md) — Resiliencia ante caídas de Google

### laravel-mcp
- [README](laravel-mcp/README.md) — Setup, módulos, comandos Artisan
- [JWT Gateway Guard](laravel-mcp/docs/jwt-gateway-guard.md) — Validación de tokens internos
- [Módulo Transaction](laravel-mcp/docs/transaction-module.md) — CRUD, export/import, modelos
- [Servidor MCP](laravel-mcp/docs/mcp-server.md) — Tools, resources, flujo completo
- [Sandbox Python](laravel-mcp/docs/sandbox.md) — Generación de documentos con IA
