# reCAPTCHA v3 con Circuit Breaker

Sistema de verificación de tokens reCAPTCHA v3 con resiliencia ante fallos del servicio de Google, manejo de errores tipado y degradación elegante.

---

## Índice

- [Visión general](#visión-general)
- [Arquitectura](#arquitectura)
- [Flujo de verificación](#flujo-de-verificación)
- [Módulo Shared — Infraestructura](#módulo-shared--infraestructura)
  - [BaseException](#baseexception)
  - [SharedErrorCode](#sharederrorcode)
  - [CircuitBreakerAction](#circuitbreakeraction)
  - [CircuitBreakerOpenException](#circuitbreakeropenexception)
- [Módulo Auth — Implementación](#módulo-auth--implementación)
  - [AuthErrorCode](#autherrorcode)
  - [RecaptchaVerificationException](#recaptchaverificationexception)
  - [RecaptchaVerificationAction](#recaptchaverificationaction)
  - [RecaptchaV3Rule](#recaptchav3rule)
  - [LoginData](#logindata)
- [Configuración](#configuración)
- [Respuestas de error](#respuestas-de-error)
- [Extender el sistema](#extender-el-sistema)
  - [Agregar un nuevo código de error](#agregar-un-nuevo-código-de-error)
  - [Agregar una nueva excepción](#agregar-una-nueva-excepción)
  - [Agregar reCAPTCHA a otro endpoint](#agregar-recaptcha-a-otro-endpoint)

---

## Visión general

El sistema resuelve dos problemas:

**1. Resiliencia ante caídas de Google reCAPTCHA**
Si la API de Google no responde o falla, el sistema activa un fallback que permite el acceso temporalmente en lugar de bloquear a todos los usuarios. Esto se implementa con el patrón Circuit Breaker.

**2. Manejo de errores tipado y centralizado**
Los códigos de error que la API retorna al cliente son `BackedEnum` — no strings sueltos. Cada módulo define sus propios códigos en un enum. Esto hace imposible que un error se retorne con un código no declarado.

---

## Arquitectura

```
Módulo Shared (infraestructura transversal)
├── Enums/
│   ├── SharedErrorCode       — códigos de error de infraestructura
│   └── CircuitBreakerStatus  — estados del circuit breaker
├── Exceptions/
│   ├── BaseException         — clase base para todas las excepciones del sistema
│   └── CircuitBreakerOpenException
├── Actions/
│   └── CircuitBreakerAction  — implementación del patrón Circuit Breaker
├── Interfaces/
│   └── CircuitBreakerStoreInterface
└── Stores/
    └── CircuitBreakerStore   — persistencia de estado en Laravel Cache

Módulo Auth (implementación de dominio)
├── Enums/
│   └── AuthErrorCode         — códigos de error del módulo Auth
├── Exceptions/
│   └── RecaptchaVerificationException
├── Actions/
│   └── RecaptchaVerificationAction
├── Rules/
│   └── RecaptchaV3Rule
└── Http/Data/
    └── LoginData
```

La dependencia es unidireccional: Auth depende de Shared, Shared no sabe nada de Auth.

---

## Flujo de verificación

```
POST /login
    │
    ▼
LoginData::__construct()
    │  valida email, password, recaptcha_token
    │  RecaptchaV3Rule ejecuta la verificación
    │
    ▼
RecaptchaV3Rule::validate()
    │  token vacío → falla con captcha_verification_required
    │  token presente → delega a RecaptchaVerificationAction
    │
    ▼
RecaptchaVerificationAction::verify()
    │
    ├─ CircuitBreakerAction estado CLOSED o HALF_OPEN
    │       │
    │       ▼
    │  verifyWithGoogle()
    │       │  HTTP POST → https://www.google.com/recaptcha/api/siteverify
    │       │
    │       ├─ respuesta HTTP error    → lanza RecaptchaVerificationException
    │       ├─ success: false          → lanza RecaptchaVerificationException
    │       ├─ action no coincide      → lanza RecaptchaVerificationException
    │       └─ score >= min_score      → retorna success: true
    │           score < min_score      → retorna success: false
    │
    │  Si verifyWithGoogle() lanza excepción:
    │       CircuitBreakerAction captura con report()
    │       registra el fallo y ejecuta fallback
    │
    └─ CircuitBreakerAction estado OPEN
            │
            ▼
       fallbackVerification()
            │  Log::warning con métricas del circuit
            └─ retorna success: true, score: 0.0, fallback_used: true
    │
    ▼
RecaptchaV3Rule recibe el resultado
    │  success: true  → validación pasa, continúa el request
    └─ success: false → falla con captcha_verification_failed
```

---

## Módulo Shared — Infraestructura

### BaseException

**Archivo:** `Modules/Shared/app/Exceptions/BaseException.php`

Clase base abstracta para todas las excepciones de dominio del sistema. Garantiza que toda excepción tenga un código de error tipado, un mensaje legible y detalles de contexto.

```php
abstract class BaseException extends Exception
{
    public function __construct(
        private readonly BackedEnum $errorCode,
        string $message = '',
        array $details = [],
    )
}
```

**Contrato de `render()`**

Cuando Laravel captura una `BaseException` no manejada, `render()` produce la respuesta JSON:

```json
{
    "error": "recaptcha_verification_failed",
    "message": "Google reCAPTCHA API no disponible",
    "details": {}
}
```

El HTTP status code lo define cada subclase via `protected $code`.

**Regla de diseño:** `$errorCode` es `private readonly BackedEnum`. No puede sobreescribirse desde una subclase ni pasarse como string. Toda excepción debe declarar su código en un enum.

---

### SharedErrorCode

**Archivo:** `Modules/Shared/app/Enums/SharedErrorCode.php`

Códigos de error para excepciones de infraestructura transversal.

| Case | Valor | Usado en |
|---|---|---|
| `BaseError` | `base_error` | Valor por defecto de `BaseException` |
| `CircuitBreakerOpen` | `circuit_breaker_open` | `CircuitBreakerOpenException` |

---

### CircuitBreakerAction

**Archivo:** `Modules/Shared/app/Actions/CircuitBreakerAction.php`

Implementa el patrón Circuit Breaker. Recibe una operación principal y un fallback. Ejecuta la operación si el circuit está disponible; si no, ejecuta el fallback directamente.

```php
$circuitBreaker = new CircuitBreakerAction('recaptcha');

$result = $circuitBreaker->call(
    operation: fn () => $this->verifyWithGoogle($token, $action),
    fallback:  fn () => $this->fallbackVerification($action),
);
```

**Estados:**

| Estado | Comportamiento |
|---|---|
| `CLOSED` | Operación normal. Todas las llamadas se ejecutan. |
| `OPEN` | Servicio fallando. Fallback inmediato sin llamar la operación. |
| `HALF_OPEN` | Intentando recuperación. Se prueba la operación gradualmente. |

**Transiciones:**

```
CLOSED ──── failures >= threshold ──→ OPEN
OPEN   ──── recovery_timeout expira ─→ HALF_OPEN
HALF_OPEN ─ successes >= threshold ──→ CLOSED
HALF_OPEN ─ failures >= threshold ───→ OPEN
```

**Manejo de errores:** cuando la operación lanza una excepción, `CircuitBreakerAction` la captura internamente con `report()` (manejador global de Laravel), registra el fallo y ejecuta el fallback. La excepción **no se propaga** al llamador — el llamador siempre recibe el resultado del fallback.

**Configuración:** `config/app.php` bajo la clave `circuit_breaker`:

```php
'circuit_breaker' => [
    'failure_threshold'  => env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 3),
    'recovery_timeout'   => env('CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 60),
    'success_threshold'  => env('CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 2),
],
```

**Inyección para tests:** el store puede inyectarse para controlar el estado sin tocar Cache:

```php
$action = new CircuitBreakerAction(
    serviceName: 'recaptcha',
    store: $customStore,
);
```

---

### CircuitBreakerOpenException

**Archivo:** `Modules/Shared/app/Exceptions/CircuitBreakerOpenException.php`

Lanzada por `CircuitBreakerAction` cuando el circuit está abierto y se intenta una operación. Extiende `BaseException` con código `SharedErrorCode::CircuitBreakerOpen`.

```php
new CircuitBreakerOpenException(
    serviceName: 'recaptcha',
    failureCount: 5,
    recoveryTimeout: 60,
);
```

---

## Módulo Auth — Implementación

### AuthErrorCode

**Archivo:** `Modules/Auth/app/Enums/AuthErrorCode.php`

Todos los códigos de error del módulo Auth. Cualquier nueva excepción o regla de validación en este módulo debe agregar su caso aquí.

| Case | Valor | Usado en |
|---|---|---|
| `RecaptchaVerificationFailed` | `recaptcha_verification_failed` | `RecaptchaVerificationException` |
| `CaptchaVerificationRequired` | `captcha_verification_required` | `RecaptchaV3Rule` — token vacío |
| `CaptchaVerificationFailed` | `captcha_verification_failed` | `RecaptchaV3Rule` — score bajo |
| `LoginFailed` | `auth_failed` | `LoginAction` |
| `LoginThrottled` | `auth_throttled` | `LoginAction` |

---

### RecaptchaVerificationException

**Archivo:** `Modules/Auth/app/Exceptions/RecaptchaVerificationException.php`

Excepción lanzada cuando la verificación con Google falla por razones controladas: token inválido, acción incorrecta, API no disponible.

- HTTP status: `503`
- Error code: `AuthErrorCode::RecaptchaVerificationFailed`

```php
throw new RecaptchaVerificationException(
    message: 'Verificación de reCAPTCHA rechazada por Google',
    details: ['error_codes' => ['invalid-input-response']],
);
```

Esta excepción es capturada por `CircuitBreakerAction` internamente — no llega al cliente directamente. Su propósito es disparar el conteo de fallos del circuit breaker.

---

### RecaptchaVerificationAction

**Archivo:** `Modules/Auth/app/Actions/RecaptchaVerificationAction.php`

Punto de entrada para la verificación de tokens. Orquesta `CircuitBreakerAction` con `verifyWithGoogle()` y `fallbackVerification()`.

```php
$action = new RecaptchaVerificationAction();

$result = $action->verify(token: $token, action: 'login');
// [
//     'success'      => true,
//     'score'        => 0.9,
//     'action'       => 'login',
//     'fallback_used'=> false,
// ]
```

**Estructura del resultado:**

| Campo | Tipo | Descripción |
|---|---|---|
| `success` | `bool` | `true` si el score supera `min_score` o si el fallback está activo |
| `score` | `float` | Score retornado por Google. `0.0` cuando se usa fallback |
| `action` | `string` | La acción verificada |
| `fallback_used` | `bool` | `true` cuando Google no estaba disponible |

**Inyección del CircuitBreakerAction:**

Para tests se puede inyectar un `CircuitBreakerAction` con estado controlado:

```php
$circuitBreaker = new CircuitBreakerAction('recaptcha-test');

$action = new RecaptchaVerificationAction($circuitBreaker);
```

---

### RecaptchaV3Rule

**Archivo:** `Modules/Auth/app/Rules/RecaptchaV3Rule.php`

Validation rule de Laravel que integra `RecaptchaVerificationAction` con la capa de validación de requests. Recibe la acción esperada como parámetro.

```php
new RecaptchaV3Rule('login')
new RecaptchaV3Rule('register')
new RecaptchaV3Rule('forgot_password')
```

**Comportamiento:**

| Situación | Resultado | Código de error |
|---|---|---|
| Token vacío o no es string | Falla | `captcha_verification_required` |
| Score bajo (Google disponible) | Falla | `captcha_verification_failed` |
| Verificación exitosa | Pasa | — |
| Circuit abierto (fallback) | Pasa | — |

El fallback siempre permite el acceso — la degradación elegante tiene prioridad sobre bloquear usuarios por problemas técnicos.

---

### LoginData

**Archivo:** `Modules/Auth/app/Http/Data/LoginData.php`

DTO de Spatie Laravel Data para el request de login. El `recaptcha_token` es nullable — si el cliente no lo envía, la validación de la Rule no se ejecuta.

```php
#[Rule(['nullable', 'string', new RecaptchaV3Rule('login')])]
public ?string $recaptcha_token
```

---

## Configuración

**Archivo:** `config/recaptchav3.php`

```php
return [
    'sitekey'                  => env('RECAPTCHAV3_SITEKEY'),
    'secret'                   => env('RECAPTCHAV3_SECRET'),
    'timeout_seconds'          => env('RECAPTCHA_TIMEOUT_SECONDS', 5),
    'url_recaptcha_site_verify'=> env('RECAPTCHAV3_URL_RECAPTCHA_SITEVERIFY',
                                      'https://www.google.com/recaptcha/api/siteverify'),
    'min_score'                => env('RECAPTCHAV3_MINSCORE', 0.5),
];
```

**Variables de entorno requeridas:**

| Variable | Descripción |
|---|---|
| `RECAPTCHAV3_SITEKEY` | Site key pública de Google reCAPTCHA |
| `RECAPTCHAV3_SECRET` | Secret key privada de Google reCAPTCHA |

**Variables opcionales:**

| Variable | Default | Descripción |
|---|---|---|
| `RECAPTCHA_TIMEOUT_SECONDS` | `5` | Timeout de la llamada HTTP a Google |
| `RECAPTCHAV3_MINSCORE` | `0.5` | Score mínimo para considerar el token válido (0.0 – 1.0) |
| `CIRCUIT_BREAKER_FAILURE_THRESHOLD` | `3` | Fallos consecutivos para abrir el circuit |
| `CIRCUIT_BREAKER_RECOVERY_TIMEOUT` | `60` | Segundos antes de intentar recuperación |
| `CIRCUIT_BREAKER_SUCCESS_THRESHOLD` | `2` | Éxitos consecutivos en HALF_OPEN para cerrar el circuit |

---

## Respuestas de error

Cuando la validación falla, Laravel retorna un `422` estándar con el código semántico como mensaje:

```json
{
    "message": "captcha_verification_required",
    "errors": {
        "recaptcha_token": ["captcha_verification_required"]
    }
}
```

Cuando una `BaseException` llega al manejador global (no capturada):

```json
{
    "error": "recaptcha_verification_failed",
    "message": "Google reCAPTCHA API no disponible",
    "details": {}
}
```

---

## Extender el sistema

### Agregar un nuevo código de error

En el enum del módulo correspondiente:

```php
// Modules/Auth/app/Enums/AuthErrorCode.php
enum AuthErrorCode: string
{
    // ...existentes
    case TwoFactorRequired = 'two_factor_required';
}
```

### Agregar una nueva excepción

```php
// Modules/Auth/app/Exceptions/TwoFactorRequiredException.php
class TwoFactorRequiredException extends BaseException
{
    protected $code = 403;

    public function __construct(string $message = '', array $details = [])
    {
        parent::__construct(
            errorCode: AuthErrorCode::TwoFactorRequired,
            message: $message,
            details: $details,
        );
    }
}
```

### Agregar reCAPTCHA a otro endpoint

1. Agregar `recaptcha_token` al Data/FormRequest correspondiente:

```php
#[Rule(['nullable', 'string', new RecaptchaV3Rule('register')])]
public ?string $recaptcha_token
```

2. La acción debe coincidir con la que el frontend genera al ejecutar `grecaptcha.execute(siteKey, { action: 'register' })`.
