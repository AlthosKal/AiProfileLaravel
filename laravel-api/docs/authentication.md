# Autenticación

Sistema de autenticación de `laravel-api`. Cubre el flujo completo desde el registro hasta el login con lockout progresivo, OAuth Google, verificación de email, gestión de contraseñas y la generación de JWT internos para comunicarse con `laravel-mcp`.

---

## Índice

- [Visión general](#visión-general)
- [Registro de usuario](#registro-de-usuario)
- [Login](#login)
  - [Flujo de seguridad](#flujo-de-seguridad)
  - [Lockout progresivo](#lockout-progresivo)
  - [Respuesta de login](#respuesta-de-login)
- [Logout](#logout)
- [OAuth Google](#oauth-google)
- [Verificación de email](#verificación-de-email)
- [Gestión de contraseñas](#gestión-de-contraseñas)
- [Middleware de seguridad](#middleware-de-seguridad)
- [JWT interno (laravel-api → laravel-mcp)](#jwt-interno-laravel-api--laravel-mcp)
- [Modelo User](#modelo-user)
- [Tablas de base de datos](#tablas-de-base-de-datos)
- [Configuración relevante](#configuración-relevante)

---

## Visión general

```
POST /login
    │
    ├─ 1. Verificar bloqueo de cuenta (temporal o permanente)
    ├─ 2. Verificar si reCAPTCHA es obligatorio (activado tras lockout)
    ├─ 3. Verificar Rate Limiter (intentos por email+IP)
    ├─ 4. Validar credenciales con Hash::check()
    ├─ 5. En fallo: RateLimiter::hit() → posible escalado de lockout
    └─ 6. En éxito: limpiar lockout, crear token Sanctum, retornar flags
```

La autenticación es **stateless**: Laravel Sanctum emite tokens de API (`personal_access_tokens`). No se usan sesiones ni cookies en ningún endpoint de la API. El token se envía en el header `Authorization: Bearer {token}` en todos los requests autenticados.

---

## Registro de usuario

**Endpoint:** `POST /api/v1/register`

**Middleware:** `guest` (rechaza si ya está autenticado), rate-limit `register_user`

**DTO:** `Modules/Auth/app/Http/Data/RegisterUserData.php`

El registro valida:
- Email no duplicado (`UserAlreadyExistsRule`)
- Contraseña segura: mínimo 8 caracteres, letras mayúsculas, minúsculas, números y símbolos
- Tipo e número de identificación (enum `IdentificationTypeEnum`: CC, CE, NIT, PP, etc.)

**Acción:** `RegisterUserAction` — crea el usuario y dispara el envío del email de verificación.

```json
// POST /api/v1/register
{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "password": "SecurePass1!",
    "password_confirmation": "SecurePass1!",
    "identification_number": 1234567890,
    "identification_type": "CC",
    "device_name": "chrome-laptop"
}
```

---

## Login

**Endpoint:** `POST /api/v1/login`

**Middleware:** `guest`, rate-limit `login`

**DTO:** `Modules/Auth/app/Http/Data/LoginData.php`

### Flujo de seguridad

El flujo está implementado en `LoginAction::login()` y sigue este orden estricto de precedencia:

```
1. CheckAccountBlockStatusHelper::check($email)
   │  ├─ security_status == TEMPORARILY_BLOCKED → 423 con tiempo restante
   │  └─ security_status == PERMANENTLY_BLOCKED → 423 sin tiempo de liberación

2. checkCaptchaRequirement($data, $ip)
   │  Si lockout activo + token no enviado → 422 captcha_verification_required
   │  Si token enviado → RecaptchaV3Rule lo valida en el DTO

3. enforceRateLimiting($key, $maxAttempts, ...)
   │  Throttle key = transliterate(email|ip)
   │  maxAttempts: 3 en primer ciclo, 1 en ciclos posteriores
   │  Si too many attempts → handleLockout() → LoginThrottledException

4. Hash::check($data->password, $user->password)
   │  Fallo → RateLimiter::hit($key, $decaySeconds)
   │           decaySeconds: 60s primer ciclo, 3600s siguientes
   │           Re-check rate limit inmediato post-hit
   │           → ValidationException (email: auth_failed)

5. Login exitoso:
   │  user->userIsLogin($email)   — actualiza last_login_at
   │  RateLimiter::clear($key)    — limpia intentos
   │  lockoutStore->clearLockoutData($email) — resetea lockout
   │  activity()->log(...)        — auditoría con spatie/activity-log
   └─ user->createToken($device_name)->plainTextToken → Sanctum token
```

### Lockout progresivo

El lockout escala en función del número de ciclos previos:

| Ciclo | maxAttempts | decaySeconds | Estado tras agotar |
|---|---|---|---|
| 1 (primer fallo) | 3 | 60 s | `TEMPORARILY_BLOCKED` (tiempo configurable) |
| 2+ | 1 | 3600 s | `TEMPORARILY_BLOCKED` → `PERMANENTLY_BLOCKED` tras N ciclos |

Tras el **primer lockout**, reCAPTCHA se activa automáticamente para el email y persiste 24 horas. En los ciclos siguientes, el usuario necesita superar el CAPTCHA además de esperar el tiempo de bloqueo.

**Clases involucradas:**
- `LockoutStateAction` — escala el estado y calcula el tiempo de bloqueo
- `LockoutStateStore` — persiste el estado en Redis (no en base de datos)
- `UserSecurityState` — vista de BD que consolida el estado actual del usuario

### Respuesta de login

```json
// 200 OK
{
    "status": "login_success",
    "data": {
        "token": "1|abc123...",
        "two_factor_required": false,
        "email_verification_required": false,
        "password_expiring_soon": false,
        "days_until_password_expires": null
    }
}
```

Los flags post-login son informativos — no interrumpen el flujo. El frontend decide si redirigir al desafío 2FA, a la verificación de email, o mostrar una advertencia de contraseña próxima a vencer.

---

## Logout

**Endpoint:** `POST /api/v1/logout`

**Middleware:** `auth:sanctum`

Revoca únicamente el token del request actual (`currentAccessToken()->delete()`). Los tokens de otros dispositivos permanecen activos.

---

## OAuth Google

**Endpoints:**
- `GET /api/v1/auth/google/redirect` — Genera la URL de redirección a Google
- `GET /api/v1/auth/google/callback` — Recibe el callback de Google

**Acción:** `GoogleOAuthAction`

El flujo usa `Laravel Socialite`. Si el email del callback coincide con un usuario existente, lo autentica. Si no existe, crea la cuenta automáticamente con `google_auth_enabled = true`.

---

## Verificación de email

**Endpoints:**
- `GET /api/v1/verify-email/{id}/{hash}` — Verifica el email con el link firmado
- `POST /api/v1/email/verification-notification` — Reenvía el email de verificación

El link es firmado (`middleware: signed`) y está rate-limited. `EnsureEmailIsNotVerified` bloquea el acceso si el email ya fue verificado.

**Middleware `EnsureEmailIsVerified`:** aplicado en rutas que requieren email verificado — retorna `403` con código `email_not_verified` si no está verificado.

---

## Gestión de contraseñas

### Reset de contraseña

1. `POST /api/v1/forgot-password` — Envía email con token de reset (`ResetPasswordMail`)
2. `POST /api/v1/reset-password` — Aplica el nuevo password con validación de historial

**`NotInPasswordHistoryRule`:** impide reutilizar cualquiera de las últimas N contraseñas (configurable). El historial se almacena hasheado en `password_histories`.

### Expiración de contraseña

El modelo `User` tiene el concern `HasPasswordExpiration`:

- `hasPasswordExpired()` — retorna `true` si `password_changed_at` supera los días configurados
- `isPasswordAboutToExpire()` — retorna `true` si quedan menos de N días (configurable)
- `getDaysUntilPasswordExpires()` — días exactos restantes

**`EnsurePasswordIsNotExpiredMiddleware`:** aplicado en rutas autenticadas que requieren contraseña vigente — retorna `403` con código `password_expired` si venció.

---

## Middleware de seguridad

| Middleware | Alias | Propósito |
|---|---|---|
| `EnsureEmailIsVerified` | — | Requiere email verificado |
| `EnsureEmailIsNotVerified` | — | Solo para usuarios sin verificar (resend) |
| `EnsurePasswordIsNotExpiredMiddleware` | — | Requiere contraseña no vencida |
| `EnsureUserIsNotBlockedMiddleware` | — | Requiere cuenta no bloqueada |

---

## JWT interno (laravel-api → laravel-mcp)

`laravel-api` genera JWTs RS256 de corta duración para autenticar sus propios requests a `laravel-mcp`. Esto permite que `laravel-mcp` confíe en que el request viene de `laravel-api` y sepa qué usuario lo originó — sin tener tabla de usuarios propia.

**Clase:** `Modules/Shared/app/Security/InternalJwtSecurity.php`

```
InternalJwtSecurity::forEmail($email)
    │
    ├─ Consulta Redis (clave: internal_jwt:sha1($email))
    │    └─ Hit → retorna JWT cacheado (TTL restante ≥ 0)
    │
    └─ Miss → generate($email)
         │  Builder JWT (lcobucci/jwt)
         │  iss = APP_URL
         │  sub = $email
         │  iat = now()
         │  exp = now() + 300s
         │  Firmado con PASSPORT_PRIVATE_KEY (RS256)
         └─ Cache::store('redis')->remember($key, 300s, ...)
```

**Claims del JWT:**

| Claim | Valor | Propósito |
|---|---|---|
| `iss` | `APP_URL` de laravel-api | Identifica el emisor |
| `sub` | Email del usuario | Identidad en laravel-mcp |
| `iat` | Timestamp de emisión | Referencia temporal |
| `exp` | `iat + 300s` | Expiración (5 minutos) |

El JWT se cachea en Redis con TTL alineado a su expiración, evitando regenerarlo en cada request del mismo usuario. Ver la documentación de validación en [`laravel-mcp/docs/jwt-gateway-guard.md`](../../laravel-mcp/docs/jwt-gateway-guard.md).

---

## Modelo User

**Archivo:** `Modules/Auth/app/Models/User.php`

El modelo `User` extiende `Authenticatable` e implementa `MustVerifyEmail`. Tiene UUID como primary key.

**Traits:**
- `HasApiTokens` (Sanctum) — tokens de API stateless
- `HasUuids` — UUID v4 como PK
- `HasFactory`
- `Notifiable`
- `LogsActivity` (Spatie) — auditoría automática de eventos
- `HasActivityLog` (concern propio) — métodos `userIsLogin()`, `userIsLogout()`
- `HasPasswordExpiration` (concern propio) — lógica de expiración
- `HasSecurityStatus` (concern propio) — bloqueo temporal/permanente

**Campos relevantes:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | UUID | Identificador único |
| `email` | string | Identificador de negocio, único |
| `security_status` | enum | `UNBLOCKED`, `TEMPORARILY_BLOCKED`, `PERMANENTLY_BLOCKED` |
| `password_changed_at` | timestamp | Para cálculo de expiración |
| `last_login_at` / `last_logout_at` | timestamp | Auditoría |
| `google_auth_enabled` | bool | Permite autenticación OAuth |
| `email_verified_at` | timestamp | null si no verificado |

---

## Tablas de base de datos

| Tabla | Propósito |
|---|---|
| `users` | Usuarios con estado de seguridad |
| `personal_access_tokens` | Tokens Sanctum por dispositivo |
| `password_histories` | Historial de contraseñas (hasheadas) |
| `user_security_events` | Eventos de seguridad (lockouts, bloqueos) |
| `user_security_state` | Vista SQL que consolida el estado de seguridad |
| `password_reset_tokens` | Tokens temporales de reset de contraseña |
| `sessions` | Sesiones web (no usadas por la API) |

---

## Configuración relevante

**`config/auth.php`**
```php
'defaults' => ['guard' => 'sanctum'],

'guards' => [
    'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
],
```

**`config/auth.php` (claves del proyecto):**

```php
// Intentos antes del primer lockout
'login' => ['ip' => ['max_attempts' => 3]],

// Días antes de que la contraseña expire
'password_expiration_days' => 90,

// Días de advertencia antes del vencimiento
'password_expiration_warning_days' => 7,

// Número de contraseñas previas que no se pueden reutilizar
'password_history_count' => 5,
```

**Variables de entorno requeridas:**

| Variable | Descripción |
|---|---|
| `PASSPORT_PRIVATE_KEY` | Clave privada RSA para firmar JWTs internos |
| `PASSPORT_PUBLIC_KEY` | Clave pública RSA (también usada en laravel-mcp) |
| `GOOGLE_CLIENT_ID` | Client ID de OAuth Google |
| `GOOGLE_CLIENT_SECRET` | Client Secret de OAuth Google |
| `RECAPTCHAV3_SITEKEY` | Site key de reCAPTCHA v3 |
| `RECAPTCHAV3_SECRET` | Secret key de reCAPTCHA v3 |
