# JWT Gateway Guard

`laravel-mcp` no tiene tabla de usuarios ni autenticación propia. Todos sus endpoints están protegidos por el guard `jwt-gateway`, que valida los JWT RS256 emitidos por `laravel-api`. Este documento describe cómo funciona la validación, cómo se construye el usuario en memoria y cómo usar el guard en nuevos endpoints.

---

## Índice

- [Visión general](#visión-general)
- [Flujo de validación](#flujo-de-validación)
- [JwtGatewayGuard](#jwtgatewayguard)
- [GatewayUser](#gatewayuser)
- [InternalJwtValidator](#internaljwtvalidator)
- [Registro del guard](#registro-del-guard)
- [Usar el guard en rutas](#usar-el-guard-en-rutas)
- [Obtener el usuario autenticado](#obtener-el-usuario-autenticado)
- [Variables de entorno](#variables-de-entorno)
- [Tests](#tests)
- [Extender GatewayUser con roles](#extender-gatewayuser-con-roles)

---

## Visión general

```
laravel-api                           laravel-mcp
──────────────────────────────        ──────────────────────────────────────────
InternalJwtSecurity::forEmail()       JwtGatewayGuard::user()
  │                                     │
  ├─ firma JWT RS256                    ├─ extrae Bearer token del header
  │   sub   = email                     ├─ parsea JWT (lcobucci/jwt)
  │   iss   = APP_URL                   ├─ verifica firma RS256 (PASSPORT_PUBLIC_KEY)
  │   exp   = now + 300s                ├─ verifica expiración (LooseValidAt)
  │   iat   = now                       ├─ verifica emisor (IssuedBy INTERNAL_API_URL)
  │                                     ├─ extrae claim sub (email)
  └─ cachea en Redis 5 min             └─ construye GatewayUser(email) en memoria
```

La clave privada solo existe en `laravel-api`. `laravel-mcp` solo tiene la clave pública. Esto garantiza que ningún otro servicio pueda forjar tokens válidos.

---

## Flujo de validación

```
Request HTTP → middleware auth:jwt-gateway → JwtGatewayGuard::user()
    │
    ├─ 1. $request->bearerToken()
    │      └─ null → retorna null → 401 automático de Laravel
    │
    ├─ 2. $jwtConfiguration->parser()->parse($token)
    │      └─ token malformado → Throwable capturado → null → 401
    │
    ├─ 3. $jwtConfiguration->validator()->assert($parsed,
    │         new SignedWith(Sha256, PASSPORT_PUBLIC_KEY),
    │         new LooseValidAt(SystemClock UTC),
    │         new IssuedBy(INTERNAL_API_URL)
    │     )
    │      └─ firma inválida / expirado / emisor incorrecto → assert falla → null → 401
    │
    ├─ 4. $parsed->claims()->get('sub')
    │      └─ vacío → retorna null → 401
    │
    └─ 5. new GatewayUser(email: $email)
           └─ $this->user = $gatewayUser → request->user() disponible
```

**Por qué `LooseValidAt` y no `StrictValidAt`:**
`lcobucci/jwt` con `StrictValidAt` requiere el claim `nbf` (not before). El builder de `InternalJwtSecurity` no lo agrega, por lo que se usa `LooseValidAt` que solo valida `exp` sin requerir `nbf`.

---

## JwtGatewayGuard

**Archivo:** `Modules/Shared/app/Security/JwtGatewayGuard.php`

Implementa `Illuminate\Contracts\Auth\Guard`. El guard es `final` y no extiende ninguna clase de Laravel — lo construye desde cero para tener control total del ciclo de validación.

**Características de diseño:**

- **Lazy**: `jwtConfiguration()` es un método privado, no una propiedad. Esto permite que en los tests el `config()` ya esté sobrescrito cuando se llama a `user()`.
- **Cacheado en request**: Una vez que `user()` resuelve correctamente un `GatewayUser`, lo guarda en `$this->user`. Los llamados subsiguientes dentro del mismo request no repiten la validación.
- **Sin excepciones al exterior**: Cualquier `Throwable` durante el parsing o la validación del JWT es capturado internamente y resulta en `null`, lo que hace que el middleware de autenticación de Laravel retorne un 401.

```php
final class JwtGatewayGuard implements Guard
{
    private ?GatewayUser $user = null;

    public function __construct(private readonly Request $request) {}

    private function jwtConfiguration(): Configuration
    {
        return Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText('empty'),               // signing key (no se usa para verificar)
            InMemory::plainText(config('passport.public_key')), // verification key
        );
    }

    public function user(): ?GatewayUser { /* ... */ }
    public function check(): bool { return $this->user() !== null; }
    public function guest(): bool { return !$this->check(); }
    public function id(): ?string { return $this->user?->email; }
    public function validate(array $credentials = []): bool { return false; }
}
```

---

## GatewayUser

**Archivo:** `Modules/Shared/app/Security/GatewayUser.php`

Representa al usuario autenticado en `laravel-mcp`. No tiene respaldo en base de datos — se construye en memoria con el email extraído del JWT.

```php
final readonly class GatewayUser implements Authenticatable
{
    public function __construct(
        public string $email,
    ) {}
}
```

**Por qué implementa `Authenticatable`:**
Laravel requiere que el objeto retornado por `Guard::user()` implemente `Authenticatable` para integrarlo con el sistema de guards y permitir `$request->user()` en controllers y policies. Los métodos de contraseña y remember token están implementados como no-op porque este sistema es stateless.

**Identificador de usuario:**
El email es el único identificador. Toda tabla en `laravel-mcp` que tenga datos de usuario usa `user_email` como foreign key (no un ID numérico ni UUID). Esto simplifica las queries porque no requiere un JOIN para obtener el email del JWT.

---

## InternalJwtValidator

**Archivo:** `Modules/Shared/app/Security/InternalJwtValidator.php`

Valida JWTs RS256 fuera del ciclo HTTP. Aplica las mismas reglas que `JwtGatewayGuard` pero está diseñada para usarse en contextos donde no existe un `Request` de Laravel.

**Cuándo usar `InternalJwtValidator` en lugar del guard:**

| Contexto | Usar |
|---|---|
| Controller/Middleware HTTP | `JwtGatewayGuard` (via `auth:jwt-gateway`) |
| Job en cola (`ShouldQueue`) | `InternalJwtValidator` |
| Consumer de Kafka | `InternalJwtValidator` |
| Comando Artisan | `InternalJwtValidator` |

```php
$validator = app(InternalJwtValidator::class);

$email = $validator->validate($jwtToken);
// null si inválido, expirado o mal firmado
// string $email si es válido
```

---

## Registro del guard

El guard se registra en `AppServiceProvider::boot()`:

```php
// app/Providers/AppServiceProvider.php
Auth::extend('jwt-gateway', function (Application $app) {
    return new JwtGatewayGuard($app['request']);
});
```

Y está configurado en `config/auth.php`:

```php
'guards' => [
    'jwt-gateway' => [
        'driver' => 'jwt-gateway',
        // Sin 'provider' porque GatewayUser no está en base de datos
    ],
],
```

---

## Usar el guard en rutas

```php
// routes/api.php — rutas protegidas por jwt-gateway
Route::prefix('v1')->middleware('auth:jwt-gateway')->group(function () {
    Route::apiResource('transactions', TransactionController::class);
});
```

Usando el enum `MiddlewaresFramework` (forma idiomática del proyecto):

```php
use Modules\Shared\Enums\MiddlewaresFramework;

Route::prefix('v1')
    ->middleware([MiddlewaresFramework::with(MiddlewaresFramework::AUTH, 'jwt-gateway')])
    ->group(function () {
        Route::apiResource('transactions', TransactionController::class);
    });
```

---

## Obtener el usuario autenticado

En cualquier controller protegido por `auth:jwt-gateway`:

```php
use Modules\Shared\Security\GatewayUser;

public function index(Request $request): JsonResponse
{
    /** @var GatewayUser $user */
    $user = $request->user();

    // $user->email — el email del usuario autenticado
    $transactions = Transaction::where('user_email', $user->email)->get();
}
```

El type-hint de `GatewayUser` en el PHPDoc es necesario porque Laravel tipifica `$request->user()` como `Authenticatable|null`, no como el tipo concreto del guard.

---

## Variables de entorno

| Variable | Descripción | Obligatoria |
|---|---|---|
| `PASSPORT_PUBLIC_KEY` | Clave pública RSA en formato PEM. Debe ser idéntica a la de `laravel-api`. | Sí |
| `INTERNAL_API_URL` | URL de `laravel-api` — se valida contra el claim `iss` del JWT. Debe incluir el schema (`http://` o `https://`). | Sí |

**Ejemplo:**
```env
PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...
-----END PUBLIC KEY-----"

INTERNAL_API_URL=http://localhost:8000
```

**Error común:** Si `INTERNAL_API_URL` no tiene el schema (`localhost:8000` en lugar de `http://localhost:8000`), la validación del claim `iss` falla y todos los requests retornan 401.

---

## Tests

Los tests del guard están en `Modules/Shared/tests/Feature/` (heredado del módulo Shared de `laravel-mcp`). Para testear endpoints protegidos:

```php
use Modules\Shared\Security\GatewayUser;

// Autenticar como un GatewayUser en tests
$this->actingAs(new GatewayUser(email: 'test@example.com'), 'jwt-gateway');

// Hacer la request
$response = $this->getJson('/api/v1/transactions');
$response->assertOk();
```

Para testear la validación del JWT directamente:

```php
// Generar un JWT de test con claves RSA inline
$config = Configuration::forAsymmetricSigner(
    new Sha256,
    InMemory::plainText($testPrivateKey),
    InMemory::plainText($testPublicKey),
);

$token = $config->builder()
    ->issuedBy('http://localhost:8000')
    ->relatedTo('test@example.com')
    ->expiresAt((new DateTimeImmutable)->modify('+5 minutes'))
    ->getToken($config->signer(), $config->signingKey())
    ->toString();

$this->withToken($token)->getJson('/api/v1/transactions');
```

---

## Extender GatewayUser con roles

Actualmente `GatewayUser` solo tiene el email. Para agregar roles en el futuro:

**1. En `laravel-api` (`InternalJwtSecurity`):** Agregar el claim al JWT:

```php
->withClaim('roles', $user->getRoles())
```

**2. En `laravel-mcp` (`JwtGatewayGuard`):** Extraer el claim al construir el usuario:

```php
$roles = $parsed->claims()->get('roles', []);
$this->user = new GatewayUser(email: $email, roles: $roles);
```

**3. En `laravel-mcp` (`GatewayUser`):** Agregar la propiedad:

```php
final readonly class GatewayUser implements Authenticatable
{
    public function __construct(
        public string $email,
        public array $roles = [],
    ) {}
}
```

No se necesita ningún cambio en la configuración de guards ni en los providers.
