<?php

namespace Modules\Auth\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Exceptions\UserNotFoundForLockoutException;
use Modules\Auth\Http\Data\LockoutStateData;
use Modules\Auth\Interfaces\LockoutStateStoreInterface;
use Modules\Auth\Models\User;
use Modules\Auth\Models\UserSecurityEvent;
use Throwable;

/**
 * Acción responsable de escalar el estado de lockout cuando se supera el Rate Limiter.
 *
 * Sistema de lockout progresivo de 3 niveles:
 *   - Nivel 1 (primer lockout):  bloqueo de 1 minuto  + activación de reCAPTCHA
 *   - Nivel 2 (segundo lockout): bloqueo de 1 hora    + reCAPTCHA sigue activo
 *   - Nivel 3 (tercer lockout):  bloqueo PERMANENTE   + registro en user_security_events
 *
 * El conteo de lockouts se almacena en cache con TTL de 24 horas, por email,
 * independientemente de la IP (para resistir rotación de IPs según OWASP).
 *
 * Esta acción es invocada exclusivamente desde LoginAction cuando se detecta
 * que se superó el Rate Limiter, no directamente desde controllers.
 */
readonly class LockoutStateAction
{
    public function __construct(
        private LockoutStateStoreInterface $store,
    ) {}

    /**
     * Incrementar el conteo de lockouts y ejecutar el nivel de escalada correspondiente.
     *
     * Usa `match` para mantener la lógica de escalada declarativa y sin condicionales anidados.
     * Los niveles 3+ caen en `default` porque desde el tercer lockout en adelante el
     * comportamiento es siempre un bloqueo permanente.
     *
     * @throws Throwable
     */
    public function handleLockout(string $email, string $ip): LockoutStateData
    {
        $count = $this->store->incrementLockoutCount($email);

        Log::info("Conteo de lockout incrementado para $email con ip $ip. El conteo fue aumentado a $count.");

        return match ($count) {
            1 => $this->handleFirstLockout($email, $ip, $count),
            2 => $this->handleSecondLockout($email, $ip, $count),
            default => $this->handleThirdLockout($email, $count, $ip),
        };
    }

    /**
     * Primer lockout: bloqueo de 1 minuto con activación de reCAPTCHA.
     *
     * Activar reCAPTCHA en el primer lockout obliga al atacante a resolver
     * un desafío humano en todos los intentos siguientes, aumentando el costo
     * del ataque sin impactar negativamente a usuarios legítimos que se equivocaron.
     */
    private function handleFirstLockout(string $email, string $ip, int $count): LockoutStateData
    {
        $duration = 60;

        // Activar reCAPTCHA para este email (persiste 24 horas en cache)
        $this->store->enableCaptcha($email);
        $this->saveExpiryTimestamp($email, $duration);

        Log::warning("Primer lockout disparado para $email con ip $ip. Bloqueo por 1 minuto con conteo $count y reCAPTCHA activado.", [
            'expires_at' => now()->addSeconds($duration)->toIso8601String(),
        ]);

        return new LockoutStateData(
            permanent: false,
            count: $count,
            errorCode: AuthErrorCode::FirstLockoutFired->value,
            captcha_enabled: true,
            duration: $duration,
            retry_after: $duration,
        );
    }

    /**
     * Segundo lockout: bloqueo de 1 hora.
     *
     * Escala el tiempo de bloqueo a 1 hora. reCAPTCHA ya está activo desde el
     * primer lockout, por lo que no se reactiva aquí. El frontend usa `captcha_enabled: true`
     * para saber que debe seguir mostrando el widget.
     */
    private function handleSecondLockout(string $email, string $ip, int $count): LockoutStateData
    {
        $duration = 3600;

        $this->saveExpiryTimestamp($email, $duration);

        Log::warning("Segundo lockout disparado para $email con ip $ip. Bloqueo por 1 hora con conteo $count.", [
            'expires_at' => now()->addSeconds($duration)->toIso8601String(),
        ]);

        return new LockoutStateData(
            permanent: false,
            count: $count,
            errorCode: AuthErrorCode::SecondLockoutFired->value,
            captcha_enabled: true,
            duration: $duration,
            retry_after: $duration,
        );
    }

    /**
     * Guardar el timestamp de expiración del bloqueo en cache.
     *
     * Se almacena como unix timestamp para que el frontend pueda calcular
     * el countdown exacto sin depender del reloj del servidor en cada request.
     * La TTL del cache coincide con la duración del bloqueo para auto-limpiar.
     */
    private function saveExpiryTimestamp(string $email, int $seconds): void
    {
        Cache::put(
            key: 'lockout:expiry:'.md5($email),
            value: now()->addSeconds($seconds)->timestamp,
            ttl: $seconds,
        );
    }

    /**
     * Tercer lockout (y posteriores): bloqueo PERMANENTE.
     *
     * En este punto se considera que la cuenta está siendo atacada de forma
     * persistente. Se ejecutan tres acciones en orden:
     *   1. Actualizar `security_status` del usuario a PERMANENTLY_BLOCKED en DB.
     *   2. Registrar el evento de seguridad en `user_security_events` para auditoría.
     *   3. Limpiar el cache de lockout (el bloqueo ahora vive en DB, no en cache).
     *
     * Si el email no corresponde a un usuario existente, se lanza
     * UserNotFoundForLockoutException para alertar de una anomalía (intentos
     * de lockout a cuentas inexistentes podría indicar enumeración de usuarios).
     *
     * @throws Throwable
     */
    private function handleThirdLockout(string $email, int $count, string $ip): LockoutStateData
    {
        $user = User::where('email', $email)->first();

        throw_if(
            ! $user,
            new UserNotFoundForLockoutException(
                message: "Usuario $email no encontrado",
                details: ['email' => $email, 'count' => $count],
            )
        );

        // Marcar el usuario como bloqueado permanentemente en la tabla users
        $user->update(['security_status' => SecurityStatusEnum::PERMANENTLY_BLOCKED->value]);

        // Crear el registro de auditoría en user_security_events antes de limpiar el cache
        $securityEvent = UserSecurityEvent::logPermanentBlock(
            user: $user,
            ipAddress: $ip,
            reason: "Bloqueo permanente automático al llegar a $count lockouts disparados",
            lockoutCount: $count,
        );

        // Limpiar el estado de cache: el bloqueo ahora es persistente en DB
        $this->store->clearLockoutData($email);

        Log::alert("Tercer lockout disparado para $email con ip $ip. Bloqueo PERMANENTE aplicado con conteo $count.");

        return new LockoutStateData(
            permanent: true,
            count: $count,
            errorCode: AuthErrorCode::ThirdLockoutFired->value,
            user_security_event: $securityEvent,
        );
    }
}
