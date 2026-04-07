<?php

namespace Modules\Auth\Actions\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Exceptions\LoginThrottledException;
use Modules\Auth\Helpers\CheckAccountBlockStatusHelper;
use Modules\Auth\Http\Data\LoginData;
use Modules\Auth\Http\Data\LoginResponseData;
use Modules\Auth\Interfaces\Auth\LockoutStateStoreInterface;
use Modules\Auth\Models\User;
use Throwable;

/**
 * Acción principal de autenticación con sistema de lockout progresivo.
 *
 * Flujo de seguridad en orden de precedencia:
 *   1. Verificar bloqueos de la cuenta (temporal → permanente)
 *   2. Exigir reCAPTCHA si ya fue activado en un lockout previo
 *   3. Verificar Rate Limiter antes de intentar autenticación
 *   4. Intentar autenticación y actualizar el Rate Limiter en caso de fallo
 *   5. Limpiar todo el estado de lockout en caso de éxito
 *   6. Evaluar verificaciones post-login (2FA, email, contraseña)
 *
 * El Rate Limiter es por email+IP para prevenir ataques de fuerza bruta
 * desde una misma IP, mientras que el lockout es por email solamente
 * para resistir rotación de IPs (OWASP Authentication Cheat Sheet).
 */
readonly class LoginAction
{
    public function __construct(
        private LockoutStateStoreInterface $lockoutStore,
        private LockoutStateAction $lockoutStateAction,
        private CheckAccountBlockStatusHelper $statusHelper,
    ) {}

    /**
     * Ejecutar el proceso completo de autenticación.
     *
     * @param  LoginData  $data  Credenciales y token reCAPTCHA del request
     * @param  string  $ip  IP del cliente para generar la throttle key
     *
     * @throws ValidationException Si las credenciales son inválidas o la cuenta está bloqueada
     * @throws Throwable
     */
    public function login(LoginData $data, string $ip): LoginResponseData
    {
        Log::debug("Intento de inicio de sesión iniciado por $data->email con número de ip $ip. ",
            ['captcha_provided' => ! empty($data->recaptcha_token),
            ]);

        // 1. Verificar bloqueos de la cuenta antes de cualquier otra validación.
        //    Si la cuenta está bloqueada se rechaza inmediatamente sin consumir
        //    intentos del Rate Limiter ni revelar si la contraseña es correcta.
        $this->statusHelper->check($data->email);

        // 2. Verificar si reCAPTCHA es obligatorio para este email.
        //    Se activa automáticamente tras el primer lockout y persiste 24 horas.
        //    Si está activo y no se envió token, se rechaza antes de llegar al Rate Limiter.
        $this->checkCaptchaRequirement($data, $ip);

        // 3. Configurar y verificar el Rate Limiter.
        //    max_attempts es progresivo: 3 intentos en el primer ciclo, 1 en los siguientes.
        //    Esto reduce la ventana de ataque después de cada lockout previo.
        $key = $this->throttleKey($data->email, $ip);
        $lockoutCount = $this->lockoutStore->getLockoutCount($data->email);
        $maxAttempts = $lockoutCount === 0 ? config('auth.login.ip.max_attempts', 3) : 1;

        Log::debug("Rate Limiter configurado para $data->email, con ip $ip, número de conteo $lockoutCount, número de máximo de intentos $maxAttempts y con el intento concurrente: ", [
            'current_attempts' => RateLimiter::attempts($key),
        ]);

        $this->enforceRateLimiting($key, $maxAttempts, $data->email, $ip);

        // 4. Verificar credenciales directamente con Hash::check().
        //    Con Sanctum API tokens no se usa Auth::attempt() porque ese método
        //    requiere un SessionGuard (web). Al ser stateless, las credenciales se
        //    validan manualmente y el token se crea en el paso 6 — sin sesión ni cookies.
        $user = User::where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            // El decay también es progresivo: 1 minuto en el primer ciclo, 1 hora después.
            // Esto alinea el tiempo de bloqueo del Rate Limiter con el del lockout.
            $decaySeconds = $lockoutCount === 0 ? 60 : 3600;

            RateLimiter::hit($key, $decaySeconds);

            $attempts = RateLimiter::attempts($key);

            Log::warning("Intento de login fallido para $data->email, con ip $ip, intento número $attempts, número de conteo $lockoutCount, número de máximo de intentos $maxAttempts y con $decaySeconds segundos de espera.");

            // Re-verificar inmediatamente después del hit: si este intento fue el que
            // agotó el límite, se dispara el lockout en esta misma request en lugar
            // de esperar al siguiente intento.
            $this->enforceRateLimiting($key, $maxAttempts, $data->email, $ip);

            throw ValidationException::withMessages([
                'email' => AuthErrorCode::LoginFailed->value,
            ]);
        }

        // 5. Login exitoso: limpiar Rate Limiter y todo el estado de lockout en cache.
        //    Esto restaura los intentos disponibles y desactiva el reCAPTCHA,
        //    permitiendo que el usuario vuelva a un flujo normal en su próxima sesión.
        //    También se agrega auditoría del ultimo inicio de sesión.
        $user->userIsLogin($user->email);
        RateLimiter::clear($key);
        $this->lockoutStore->clearLockoutData($data->email);

        activity('Inicio de sesión del Usuario')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->log("Usuario $user->name, con correo $user->email, con número de identificación $user->identification_number y con tipo de identificación $user->identification_type inició sesión correctamente el $user->last_login_at desde la ip $ip");

        // 6. Crear el token Sanctum y construir las flags de estado post-login.
        //    El plainTextToken solo está disponible en este momento — en DB se guarda
        //    su hash SHA-256 y no puede recuperarse después.
        //    Las flags no interrumpen el flujo — el frontend decide cómo reaccionar.

        return new LoginResponseData(
            token: $user->createToken($data->device_name)->plainTextToken,
            twoFactorRequired: $user->hasTwoFactorEnabled(),
            emailVerificationRequired: ! $user->hasVerifiedEmail(),
            passwordExpiringSoon: $this->isPasswordAboutToExpire($user),
            daysUntilPasswordExpires: $user->getDaysUntilPasswordExpires(),
        );
    }

    /**
     * Disparar el lockout si se superó el límite de intentos para esta key.
     *
     * Se llama en dos momentos distintos del flujo:
     *   - Antes del intento de autenticación: rechaza si ya estaba bloqueado.
     *   - Después del `RateLimiter::hit()` fallido: detecta si este intento agotó el límite.
     *
     * Cuando se supera el límite, delega a LockoutStateAction para escalar el estado
     * (primer lockout → segundo → bloqueo permanente) y lanza LoginThrottledException
     * con los datos del nuevo estado para que el frontend pueda reaccionar.
     *
     * @throws LoginThrottledException
     * @throws Throwable
     */
    private function enforceRateLimiting(string $key, int $maxAttempts, string $email, string $ip): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        Log::warning("Rate Limiter superado para $email con ip $ip. Escalando lockout.", [
            'attempts' => RateLimiter::attempts($key),
            'max_attempts' => $maxAttempts,
        ]);

        // Disparar el evento nativo de Laravel para compatibilidad con listeners externos
        // (e.g. notificaciones, auditoría) que escuchen Illuminate\Auth\Events\Lockout.
        event(new Lockout(request()));

        $lockoutState = $this->lockoutStateAction->handleLockout($email, $ip);

        throw new LoginThrottledException($lockoutState);
    }

    /**
     * Verificar si reCAPTCHA es obligatorio y si el token fue provisto.
     *
     * reCAPTCHA se activa tras el primer lockout y permanece activo 24 horas.
     * Si está activo pero no se envió token, se rechaza con un error en el campo
     * `recaptcha_token` para que el frontend pueda mostrar el widget.
     *
     * La validación del score del token la realiza RecaptchaV3Rule en el DTO
     * de entrada (LoginData), por lo que aquí solo se verifica la presencia.
     *
     * @throws ValidationException
     */
    private function checkCaptchaRequirement(LoginData $data, string $ip): void
    {
        if (! $this->lockoutStore->isCaptchaRequired($data->email)) {
            return;
        }

        if (empty($data->recaptcha_token)) {
            Log::warning("Login rechazado para $data->email con ip $ip: reCAPTCHA requerido pero no provisto.");

            throw ValidationException::withMessages([
                'recaptcha_token' => AuthErrorCode::CaptchaVerificationRequired->value,
            ]);
        }
    }

    /**
     * Verificar si la contraseña del usuario está próxima a vencer.
     *
     * Se considera "próxima a vencer" cuando los días restantes están dentro
     * de la ventana de advertencia configurada en `auth.password_expiration_warning_days`.
     * El frontend puede mostrar un aviso no bloqueante con los días exactos restantes.
     * Si la contraseña ya venció (`hasPasswordExpired`) no aplica esta advertencia
     * ya que ese caso se maneja con un middleware de expiración.
     */
    private function isPasswordAboutToExpire(User $user): bool
    {
        if ($user->hasPasswordExpired()) {
            return false;
        }

        return $user->isPasswordAboutToExpire();
    }

    /**
     * Generar la throttle key combinando email e IP.
     *
     * La key combina email + IP para que cada dispositivo tenga su propia
     * ventana de intentos. `Str::transliterate` normaliza caracteres Unicode
     * para evitar colisiones con emails que contengan acentos u otros caracteres especiales.
     */
    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
