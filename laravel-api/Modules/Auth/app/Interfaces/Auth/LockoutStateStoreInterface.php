<?php

namespace Modules\Auth\Interfaces\Auth;

interface LockoutStateStoreInterface
{
    /**
     * Obtener contador de lockouts
     */
    public function getLockoutCount(string $email): int;

    /**
     * Incrementar contador de Lockouts
     */
    public function incrementLockoutCount(string $email): int;

    /**
     * Verificar si reCAPTCHA es requerido
     */
    public function isCaptchaRequired(string $email): bool;

    /**
     * Activar reCAPTCHA (persiste 24 Horas)
     */
    public function enableCaptcha(string $email): void;

    public function clearLockoutData(string $email): void;
}
