# Arquitectura modular

`laravel-api` usa `nwidart/laravel-modules` para organizar el código en módulos independientes. Cada módulo es un mini-aplicación Laravel con su propio directorio de rutas, migraciones, providers, tests y lógica de dominio.

---

## Índice

- [Estructura de un módulo](#estructura-de-un-módulo)
- [Módulos del proyecto](#módulos-del-proyecto)
- [Dependencias entre módulos](#dependencias-entre-módulos)
- [Convenciones de código](#convenciones-de-código)
- [Generador de módulos](#generador-de-módulos)
- [Agregar un nuevo módulo](#agregar-un-nuevo-módulo)
- [Agregar una nueva funcionalidad a un módulo existente](#agregar-una-nueva-funcionalidad-a-un-módulo-existente)

---

## Estructura de un módulo

```
Modules/NombreModulo/
├── app/
│   ├── Actions/          # Lógica de negocio (un archivo por caso de uso)
│   ├── Enums/            # BackedEnums para códigos, tipos, estados
│   ├── Exceptions/       # Excepciones de dominio (extienden BaseException)
│   ├── Http/
│   │   ├── Controllers/  # Controllers delgados — solo orquestan Actions
│   │   ├── Data/         # DTOs de Spatie Laravel Data (request + response)
│   │   ├── Middleware/   # Middleware específico del módulo
│   │   └── Requests/     # FormRequests para validación compleja
│   ├── Interfaces/       # Contratos (para inyección de dependencias)
│   ├── Models/           # Modelos Eloquent
│   ├── Providers/
│   │   ├── {Nombre}ServiceProvider.php  # Provider principal del módulo
│   │   ├── EventServiceProvider.php
│   │   └── RouteServiceProvider.php
│   ├── Rules/            # Validation rules de Laravel
│   └── Stores/           # Implementaciones de Interfaces (Redis, DB, etc.)
├── config/
│   └── config.php        # Configuración del módulo
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/
│   └── views/            # Vistas Blade (emails, etc.)
├── routes/
│   ├── api.php           # Rutas de API del módulo
│   ├── auth.php          # Rutas de autenticación (solo módulo Auth)
│   └── web.php           # Rutas web (generalmente vacías)
└── tests/
    └── Feature/          # Tests de integración del módulo
```

---

## Módulos del proyecto

### `Shared`

**Propósito:** Infraestructura transversal usada por todos los módulos. No contiene lógica de dominio.

**No tiene:**
- Rutas de API propias
- Modelos de negocio
- Dependencia hacia otros módulos del proyecto

**Contiene:**

| Componente | Clase | Descripción |
|---|---|---|
| Seguridad | `InternalJwtSecurity` | Genera y cachea JWT RS256 para laravel-mcp |
| Resiliencia | `CircuitBreakerAction` | Circuit Breaker reutilizable (Redis store) |
| Rate limiting | `RateLimiterForApp` | Helper para configurar rate limiters por nombre |
| Errores | `BaseException` | Clase base tipada para excepciones de dominio |
| Enums | `MiddlewaresFramework` | Alias de middlewares de Laravel como enum |
| Eventos | `CircuitBreakerOpenedEvent`, etc. | Eventos de cambio de estado del circuit breaker |

**Regla:** Si un componente es usado por más de un módulo, pertenece a `Shared`.

---

### `Auth`

**Propósito:** Gestión completa del ciclo de vida del usuario: registro, autenticación, OAuth, verificación de email, contraseñas y seguridad.

**Depende de:** `Shared`

**Modelos:** `User`, `PasswordHistory`, `UserSecurityEvent`, `UserSecurityState` (vista)

**Concerns del modelo `User`:**

| Concern | Archivo | Propósito |
|---|---|---|
| `HasPasswordExpiration` | `app/Models/Concerns/` | Expiración configurable de contraseña |
| `HasSecurityStatus` | `app/Models/Concerns/` | Bloqueo temporal y permanente |
| `HasActivityLog` | `app/Models/Concerns/` | `userIsLogin()`, `userIsLogout()` |
| `LogsSecurityEvents` | `app/Models/Concerns/` | Registra eventos de seguridad en tabla propia |

**Enums principales:**

| Enum | Valores | Usado en |
|---|---|---|
| `AuthErrorCode` | `auth_failed`, `auth_throttled`, `captcha_verification_required`, etc. | Excepciones y respuestas de error |
| `SecurityStatusEnum` | `UNBLOCKED`, `TEMPORARILY_BLOCKED`, `PERMANENTLY_BLOCKED` | Columna `users.security_status` |
| `IdentificationTypeEnum` | `CC`, `CE`, `NIT`, `PP`, etc. | Columna `users.identification_type` |
| `LockoutStatePrefixEnum` | — | Prefijos de claves Redis para el lockout |
| `PasswordResetReason` | — | Razón del reset de contraseña |

---

### `Client`

**Propósito:** Integración con `laravel-mcp` y con el proveedor de IA. Expone al frontend las operaciones de transacciones y el endpoint del agente.

**Depende de:** `Shared`, `Auth` (usa el modelo `User` para obtener el email)

**Dos capas de integración con laravel-mcp:**

1. **REST HTTP** (`HttpClient`) — Para operaciones CRUD de transacciones. El frontend llama a `laravel-api`, que llama a `laravel-mcp` añadiendo el JWT interno.

2. **MCP Protocol** (`AiAssistantMcpClient`) — Para el agente IA. Establece una sesión MCP con el servidor `AiAssistantServer` de `laravel-mcp`.

**Herramientas MCP disponibles (descubiertas dinámicamente):**

El `McpToolRegistry` mapea los nombres de tools del servidor MCP a clases PHP proxy en `app/Ai/Tools/`. Cada clase es una subclase de `McpProxyTool` cuyo nombre de clase coincide exactamente con el nombre de la tool en el servidor.

```
McpToolRegistry::TOOL_MAP = [
    'GetAllTransactionsTool'           → GetAllTransactionsTool::class,
    'GetTransactionsByPeriodTool'      → GetTransactionsByPeriodTool::class,
    'GetTransactionsByAmountRangeTool' → GetTransactionsByAmountRangeTool::class,
    'GetTransactionByTypeTool'         → GetTransactionByTypeTool::class,
    'RequestDocumentGenerationTool'    → RequestDocumentGenerationTool::class,
    'CheckDocumentStatusTool'          → CheckDocumentStatusTool::class,
]
```

**Tavily MCP** (`TavilyMcpClient` + `TavilyToolRegistry`): Conexión al servidor MCP externo de Tavily para búsqueda web en tiempo real. Las tools disponibles son `tavily-search`, `tavily-extract`, `tavily-map`, `tavily-crawl`.

---

## Dependencias entre módulos

```
Shared ←── Auth
       ←── Client ←── Auth (modelo User)
```

- `Shared` no depende de ningún módulo del proyecto.
- `Auth` depende de `Shared` (usa `BaseException`, `CircuitBreakerAction`, `InternalJwtSecurity`).
- `Client` depende de `Shared` (usa `InternalJwtSecurity`) y de `Auth` (usa el modelo `User`).

**Regla de oro:** Las dependencias solo fluyen hacia módulos más fundamentales. Un módulo no puede depender de uno "paralelo" o "superior".

---

## Convenciones de código

### Actions

Las acciones son clases `readonly` con un único método público que representa el caso de uso:

```php
readonly class LoginAction
{
    public function __construct(
        private LockoutStateStoreInterface $lockoutStore,
        private LockoutStateAction $lockoutStateAction,
        private CheckAccountBlockStatusHelper $statusHelper,
    ) {}

    public function login(LoginData $data, string $ip): LoginResponseData
    {
        // ...
    }
}
```

- Una acción = un caso de uso
- El método principal se nombra con el verbo del caso de uso: `login()`, `add()`, `export()`
- Los controllers inyectan la acción y la invocan directamente

### DTOs (Data)

Los DTOs usan `Spatie Laravel Data`. Se declaran con `#[Rule]` para validación inline:

```php
class LoginData extends Data
{
    public function __construct(
        #[Rule(['required', 'email'])]
        public string $email,

        #[Rule(['required', 'string', 'min:8'])]
        public string $password,

        #[Rule(['nullable', 'string', new RecaptchaV3Rule('login')])]
        public ?string $recaptcha_token,

        #[Rule(['required', 'string'])]
        public string $device_name,
    ) {}
}
```

### Controllers

Los controllers son delegadores. No contienen lógica de negocio:

```php
class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly LoginAction $action
    ) {}

    public function store(LoginData $data): JsonResponse
    {
        $result = AuthenticatedSessionResponseData::fromLoginResponse(
            $this->action->login($data, request()->ip())
        );

        return $this->success(AuthSuccessCode::LoginSuccess->value, $result);
    }
}
```

### Excepciones

Todas las excepciones de dominio extienden `BaseException` y declaran su código de error en un `BackedEnum`:

```php
class LoginThrottledException extends BaseException
{
    protected $code = 429;

    public function __construct(LockoutStateData $lockoutState)
    {
        parent::__construct(
            errorCode: AuthErrorCode::LoginThrottled,
            message: 'Too many login attempts.',
            details: $lockoutState->toArray(),
        );
    }
}
```

### Enums de errores

Cada módulo declara sus propios códigos de error en un `BackedEnum`. El valor del case es el string que llega al cliente:

```php
enum AuthErrorCode: string
{
    case LoginFailed               = 'auth_failed';
    case LoginThrottled            = 'auth_throttled';
    case RecaptchaVerificationFailed = 'recaptcha_verification_failed';
    case CaptchaVerificationRequired = 'captcha_verification_required';
    case CaptchaVerificationFailed   = 'captcha_verification_failed';
}
```

---

## Generador de módulos

El proyecto incluye comandos Artisan para generar código con la estructura correcta:

```bash
# Crear módulo completo (estructura de carpetas + providers)
php artisan module:make NombreModulo

# Crear una Action en un módulo
php artisan module:make-action Auth LoginAction

# Crear un Data (DTO) en un módulo
php artisan module:make-data Auth LoginData
```

Los generadores se encuentran en:
- `app/Console/Commands/ModuleMakeCommand.php`
- `app/Console/Commands/ModuleMakeActionCommand.php`
- `app/Console/Commands/ModuleMakeDataCommand.php`

---

## Agregar un nuevo módulo

1. **Generar el módulo:**
   ```bash
   php artisan module:make Billing
   ```

2. **Registrar el módulo** en `modules_statuses.json` (se hace automáticamente):
   ```json
   { "Billing": true }
   ```

3. **Crear las migraciones** con el prefijo de fecha:
   ```bash
   php artisan make:migration create_invoices_table --path=Modules/Billing/database/migrations
   ```

4. **Agregar las rutas** en `Modules/Billing/routes/api.php`

5. **Registrar las rutas** en `Modules/Billing/app/Providers/RouteServiceProvider.php`

---

## Agregar una nueva funcionalidad a un módulo existente

### Nuevo endpoint

1. Crear el DTO: `php artisan module:make-data Client GetAgentHistoryData`
2. Crear la Action: `php artisan module:make-action Client GetAgentHistoryAction`
3. Crear o editar el Controller en `Modules/Client/app/Http/Controllers/`
4. Agregar la ruta en `Modules/Client/routes/api.php`
5. Escribir el test en `Modules/Client/tests/Feature/`

### Nueva tool MCP (en laravel-mcp, registrarla en laravel-api)

Cuando se agrega una nueva tool al servidor MCP (`laravel-mcp`):

1. En `laravel-mcp`: crear la tool en `Modules/Transaction/app/Mcp/Tools/`
2. En `laravel-mcp`: registrarla en `AiAssistantServer::$tools`
3. En `laravel-api`: crear la subclase proxy en `Modules/Client/app/Ai/Tools/`
4. En `laravel-api`: registrarla en `McpToolRegistry::TOOL_MAP`

El nombre de la clase proxy debe coincidir exactamente con el nombre que el servidor MCP expone en `listTools()`.
