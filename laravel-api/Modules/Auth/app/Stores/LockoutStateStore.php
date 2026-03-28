<?php

namespace Modules\Auth\Stores;

use Cache;
use Log;
use Modules\Auth\Enums\LockoutStatePrefixEnum;
use Modules\Auth\Enums\LockoutStateTimeEnum;
use Modules\Auth\Interfaces\LockoutStateStoreInterface;

class LockoutStateStore implements LockoutStateStoreInterface
{
    /**
     * Obtener contador de lockouts
     */
    public function getLockoutCount(string $email): int
    {
        $key = $this->getKey(LockoutStatePrefixEnum::PREFIX_LOCKOUT_COUNT->value, $email);

        return (int) Cache::get($key, 0);
    }

    /**
     * Incrementar contador de Lockouts
     */
    public function incrementLockoutCount(string $email): int
    {
        $key = $this->getKey(LockoutStatePrefixEnum::PREFIX_LOCKOUT_COUNT->value, $email);

        // Cache::add inicializa la clave con TTL solo si no existe.
        // Cache::increment a secas no aplica TTL — combinados resuelven ambos problemas.
        Cache::add($key, 0, LockoutStateTimeEnum::TTL_24H->value);
        $count = Cache::increment($key);

        Log::info("Conteo para el bloqueo incrementado para: $email");

        return $count;
    }

    /**
     * Verificar si reCAPTCHA es requerido
     */
    public function isCaptchaRequired(string $email): bool
    {
        $key = $this->getKey(LockoutStatePrefixEnum::PREFIX_CAPTCHA->value, $email);

        return (bool) Cache::get($key);
    }

    /**
     * Activar reCAPTCHA (persiste 24 Horas)
     */
    public function enableCaptcha(string $email): void
    {
        $key = $this->getKey(LockoutStatePrefixEnum::PREFIX_CAPTCHA->value, $email);
        Cache::put($key, true, LockoutStateTimeEnum::TTL_24H->value);
        Log::info("reCAPTCHA activado para: $email");
    }

    public function clearLockoutData(string $email): void
    {
        Cache::forget($this->getKey(LockoutStatePrefixEnum::PREFIX_LOCKOUT_COUNT->value, $email));
        Cache::forget($this->getKey(LockoutStatePrefixEnum::PREFIX_CAPTCHA->value, $email));
        Cache::forget('lockout:expiry:'.md5($email));
    }

    /**
     *  Generar key única para Redis basada solo en email.
     *
     *  Deliberadamente, NO incluye IP: el contador de lockouts debe acumularse
     *  por cuenta para resistir rotación de IPs (OWASP Authentication Cheat Sheet).
     */
    private function getKey(string $prefix, string $email): string
    {
        return $prefix.md5($email);
    }
}
